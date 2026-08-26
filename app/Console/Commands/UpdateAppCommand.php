<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Throwable;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\warning;

class UpdateAppCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:update';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Updates the application (git pull, composer, npm, migrations, etc.)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $nonInteractive = (bool) $this->option('no-interaction');

        info('Iniciando processo de atualização do James...');

        // 0. Verificar dependências
        $requiredCommands = ['git', 'php', 'composer', 'npm'];
        foreach ($requiredCommands as $cmd) {
            if (! Process::run("command -v $cmd")->successful()) {
                error("Erro: O comando '{$cmd}' não está instalado ou não está no PATH.");

                return self::FAILURE;
            }
        }
        // 1. Verificar atualizações no Git
        if ($this->hasGitUpdates()) {
            info('Novas atualizações encontradas no repositório.');
        } else {
            warning('O repositório local já está na versão mais recente.');

            $force = $nonInteractive || confirm(
                label: 'Deseja forçar o processo de atualização (build, otimizações, migrations) mesmo assim?',
                default: false
            );

            if (! $force) {
                info('Atualização cancelada pelo usuário.');

                return self::SUCCESS;
            }
        }

        try {
            // Coloca a aplicação em modo de manutenção
            $this->call('down');
            info('Aplicação em modo de manutenção.');

            // 2. Git Pull (apenas se não estiver forçando sem mudanças)
            spin(
                function () {
                    $process = Process::run('git pull');
                    if ($process->failed()) {
                        throw new \Exception('Falha ao fazer git pull: '.$process->errorOutput());
                    }
                },
                'Atualizando código fonte...'
            );

            // 3. Composer Install
            spin(
                function () {
                    $composerCmd = 'composer install --no-interaction --optimize-autoloader';
                    if (app()->isProduction()) {
                        $composerCmd .= ' --no-dev';
                    }
                    $process = Process::run($composerCmd);
                    if ($process->failed()) {
                        throw new \Exception('Falha no Composer: '.$process->errorOutput());
                    }
                },
                'Instalando dependências do PHP (Composer)...'
            );

            // 4. NPM Install
            spin(
                function () {
                    $process = Process::run('npm ci');
                    if ($process->failed()) {
                        throw new \Exception('Falha no NPM ci: '.$process->errorOutput());
                    }
                },
                'Instalando dependências do Front-end (NPM)...'
            );

            // 5. NPM Build
            spin(
                function () {
                    $process = Process::run('npm run build');
                    if ($process->failed()) {
                        throw new \Exception('Falha no NPM build: '.$process->errorOutput());
                    }
                },
                'Compilando assets (Vite)...'
            );

            // 6. Migrations
            spin(
                function () {
                    $this->callSilent('migrate', ['--force' => true]);
                },
                'Executando migrações do banco de dados...'
            );

            // 7. Storage Link
            spin(
                function () {
                    if (! is_link(public_path('storage'))) {
                        $this->callSilent('storage:link', ['--relative' => true]);
                    }
                },
                'Criando link simbólico do storage...'
            );

            // 8. Seeders (Opcional)
            $runSeeders = ! $nonInteractive && confirm(
                label: 'Deseja rodar as seeds padrão (usuário inicial e tags)? Recomendado no 1º deploy.',
                default: false
            );

            if ($runSeeders) {
                spin(
                    function () {
                        $this->callSilent('db:seed', ['--force' => true]);
                    },
                    'Populando o banco de dados...'
                );
                info('Seeds executadas com sucesso.');
            }

            // 9. Otimização do Laravel
            spin(
                function () {
                    $this->callSilent('optimize:clear');
                    $this->callSilent('optimize');
                },
                'Otimizando o Laravel (Cache)...'
            );

            // 10. Restart do Supervisor
            $supervisorFailed = false;
            spin(
                function () use (&$supervisorFailed) {
                    if (Process::run('command -v supervisorctl')->successful()) {
                        $process = Process::run('sudo -n supervisorctl restart all');
                        if ($process->failed()) {
                            $supervisorFailed = true;
                        }
                    }
                },
                'Reiniciando processos em background (Supervisor)...'
            );

            if ($supervisorFailed) {
                warning('Não foi possível reiniciar o Supervisor automaticamente (requer sudo sem senha).');
                warning('Por favor, rode o comando manualmente: sudo supervisorctl restart all');
            }

            info('Atualização concluída com sucesso! 🚀');

        } catch (Throwable $e) {
            error('Erro durante a atualização: '.$e->getMessage());
            $this->newLine();
            error('O processo foi interrompido.');
        } finally {
            // Garante que a aplicação sairá do modo de manutenção
            $this->call('up');
            info('Aplicação ativa novamente.');
        }

        return self::SUCCESS;
    }

    /**
     * Verifica se há atualizações no repositório remoto.
     */
    private function hasGitUpdates(): bool
    {
        // Se não for um repositório git, pula essa checagem
        if (! is_dir(base_path('.git'))) {
            return false;
        }

        try {
            Process::run('git fetch origin');
            $currentBranchProcess = Process::run('git rev-parse --abbrev-ref HEAD');
            $currentBranch = trim($currentBranchProcess->output());

            $localHashProcess = Process::run('git rev-parse HEAD');
            $localHash = trim($localHashProcess->output());

            $remoteHashProcess = Process::run("git rev-parse origin/{$currentBranch}");
            $remoteHash = trim($remoteHashProcess->output());

            return $localHash !== $remoteHash;
        } catch (Throwable) {
            return false;
        }
    }
}
