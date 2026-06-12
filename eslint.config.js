import js from '@eslint/js';
import stylistic from '@stylistic/eslint-plugin';
import prettier from 'eslint-config-prettier/flat';
import importPlugin from 'eslint-plugin-import';
import vue from 'eslint-plugin-vue';

const browserGlobals = {
    window: 'readonly',
    document: 'readonly',
    navigator: 'readonly',
    localStorage: 'readonly',
    sessionStorage: 'readonly',
    console: 'readonly',
    setTimeout: 'readonly',
    clearTimeout: 'readonly',
    setInterval: 'readonly',
    clearInterval: 'readonly',
    requestAnimationFrame: 'readonly',
    cancelAnimationFrame: 'readonly',
    fetch: 'readonly',
    URL: 'readonly',
    URLSearchParams: 'readonly',
    IntersectionObserver: 'readonly',
    matchMedia: 'readonly',
};

export default [
    js.configs.recommended,
    ...vue.configs['flat/essential'],
    {
        files: ['**/*.{js,mjs,vue}'],
        plugins: { import: importPlugin },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: browserGlobals,
        },
        rules: {
            'vue/multi-word-component-names': 'off',
            curly: ['error', 'all'],
            'import/order': [
                'error',
                {
                    groups: ['builtin', 'external', 'internal', 'parent', 'sibling', 'index'],
                    'newlines-between': 'always',
                    alphabetize: { order: 'asc', caseInsensitive: true },
                },
            ],
        },
    },
    {
        plugins: { '@stylistic': stylistic },
        rules: {
            '@stylistic/brace-style': ['error', '1tbs', { allowSingleLine: false }],
        },
    },
    prettier,
    {
        ignores: ['vendor', 'node_modules', 'public', 'bootstrap/ssr', 'docs/design-ref'],
    },
];
