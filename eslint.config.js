import js from '@eslint/js';
import globals from 'globals';
import react from 'eslint-plugin-react';
import reactHooks from 'eslint-plugin-react-hooks';

export default [
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'storage/**', 'bootstrap/cache/**'] },
    js.configs.recommended,
    {
        files: ['resources/js/**/*.{js,jsx}'],
        plugins: { react, 'react-hooks': reactHooks },
        languageOptions: {
            ecmaVersion: 'latest',
            sourceType: 'module',
            globals: { ...globals.browser },
            parserOptions: { ecmaFeatures: { jsx: true } },
        },
        settings: { react: { version: 'detect' } },
        rules: {
            ...react.configs.recommended.rules,
            ...react.configs['jsx-runtime'].rules,
            // The two classic hook rules catch real bugs, so they are errors.
            'react-hooks/rules-of-hooks': 'error',
            'react-hooks/exhaustive-deps': 'warn',
            // No PropTypes in this codebase; not worth adding a dependency for.
            'react/prop-types': 'off',
            // Apostrophes and quotes in JSX text are valid and render correctly.
            'react/no-unescaped-entities': 'off',
            // Unused variables are bugs waiting to happen; allow a leading underscore to opt out.
            'no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            // Empty catch blocks are used deliberately around localStorage and fetch.
            'no-empty': ['error', { allowEmptyCatch: true }],
        },
    },
    {
        // Config files run in Node, not the browser.
        files: ['*.config.js'],
        languageOptions: { globals: { ...globals.node } },
    },
];
