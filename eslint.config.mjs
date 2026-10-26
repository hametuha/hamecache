import wpPlugin from '@wordpress/eslint-plugin';
import globals from 'globals';

export default [
	{
		ignores: [ 'assets/**', 'vendor/**', 'node_modules/**', 'wordpress/**' ],
	},
	...wpPlugin.configs[ 'recommended-with-formatting' ],
	{
		languageOptions: {
			globals: {
				...globals.browser,
				...globals.jquery,
				wp: 'readonly',
			},
		},
		rules: {
			// Keep existing msgids ("...") so translations on translate.wordpress.org stay valid.
			'@wordpress/i18n-ellipsis': 'off',
		},
	},
];
