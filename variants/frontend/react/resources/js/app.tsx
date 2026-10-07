import { createInertiaApp } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) => {
        // `*.blocks-*.tsx` são páginas prontas e inertes (dependem de componentes
        // shadcn que só o comando de instalação do bloco traz): ficam fora do bundle.
        const pages = import.meta.glob(['./pages/**/*.tsx', '!./pages/**/*.blocks-*.tsx'], { eager: true });
        return pages[`./pages/${name}.tsx`];
    },
    setup({ el, App, props }) {
        createRoot(el).render(<App {...props} />);
    },
});
