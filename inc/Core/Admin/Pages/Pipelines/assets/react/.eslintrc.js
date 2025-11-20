module.exports = {
  extends: ['@wordpress/eslint-plugin/recommended'],
  rules: {
    // Enforce data access patterns
    'no-restricted-imports': [
      'error',
      {
        paths: [
          {
            name: '@tanstack/react-query',
            importNames: ['useQuery', 'useMutation', 'useInfiniteQuery'],
            message: 'Presentational components should receive data as props, not fetch with TanStack Query hooks. Use containers for data fetching.'
          }
        ],
        // Allow in container files and approved exceptions
        allowedFiles: [
          '**/PipelinesApp.jsx',
          '**/FlowsSection.jsx',
          '**/FlowCard.jsx',
          '**/modals/**',
          '**/queries/**'
        ]
      }
    ],
    // Warn about console usage in production code
    'no-console': 'warn',
    // Enforce consistent import ordering
    'import/order': [
      'error',
      {
        groups: [
          'builtin',
          'external',
          'internal',
          'parent',
          'sibling',
          'index'
        ],
        'newlines-between': 'always'
      }
    ]
  },
  settings: {
    'import/resolver': {
      node: {
        paths: ['.'],
        extensions: ['.js', '.jsx', '.ts', '.tsx']
      }
    }
  }
};