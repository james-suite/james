import Alpine from 'alpinejs';
import nfceImport from './nfce-import';

let echartsLoader;
let easyMdeLoader;
let cropperLoader;

window.loadEcharts = () => {
    echartsLoader ??= import('echarts').then((module) => {
        window.echarts = module;

        return module;
    });

    return echartsLoader;
};

window.loadEasyMDE = () => {
    easyMdeLoader ??= Promise.all([
        import('easymde'),
        import('easymde/dist/easymde.min.css'),
    ]).then(([module]) => module.default);

    return easyMdeLoader;
};

window.loadCropper = () => {
    cropperLoader ??= Promise.all([
        import('cropperjs'),
        import('cropperjs/dist/cropper.css'),
    ]).then(([module]) => module.default);

    return cropperLoader;
};

window.Alpine = Alpine;

Alpine.data('nfceImport', nfceImport);

Alpine.start();
