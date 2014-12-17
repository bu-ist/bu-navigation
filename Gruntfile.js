/* jshint node:true */
module.exports = function(grunt) {

	// Load tasks.
	require('matchdep').filterDev(['grunt-*']).forEach( grunt.loadNpmTasks );

	// Project configuration.
	grunt.initConfig({
		uglify: {
			core: {
				expand: true,
				cwd: 'js',
				dest: 'js',
				ext: '.min.js',
				src: ['*.js', '!*.min.js']
			},
			vendor: {
				expand: true,
				cwd: 'js/vendor',
				dest: 'js/vendor',
				ext: '.min.js',
				src: ['*.js', '!*.min.js'],
				extDot: 'last'
			}
		},
		watch: {
			scripts: {
				files: ['js/**/*.js', '!js/**/*.min.js'],
				tasks: ['uglify']
			}
		}
	});

	// Build task.
	grunt.registerTask('build', ['uglify']);

	// Default task.
	grunt.registerTask('default', ['build']);
}