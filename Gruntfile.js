module.exports = function(grunt) {
  grunt.initConfig({
      // Path configuration
      paths: {
          src: 'amd/src/',
          dest: 'amd/build/'
      },

      // Rollup configuration for AMD
      rollup: {
          options: {
              format: 'amd',
              sourcemap: true,
              plugins: [
                  // Add any Rollup plugins here if needed
              ]
          },
          dist: {
              files: [{
                  expand: true,
                  cwd: '<%= paths.src %>',
                  src: '**/*.js',
                  dest: '<%= paths.dest %>',
                  ext: '.min.js'
              }]
          }
      },

      // ESLint (if needed for linting)
      eslint: {
          amd: ['<%= paths.src %>/**/*.js']  // Specify the files to lint
      },

      // Watch task (optional, for automatic recompilation)
      watch: {
          js: {
              files: ['<%= paths.src %>/**/*.js'],
              tasks: ['eslint', 'rollup'],
              options: {
                  spawn: false
              }
          }
      }
  });

  // Load necessary Grunt plugins
  grunt.loadNpmTasks('grunt-eslint');
  grunt.loadNpmTasks('grunt-rollup');
  grunt.loadNpmTasks('grunt-contrib-watch');

  // Default task(s)
  grunt.registerTask('amd', ['eslint', 'rollup']);
};
