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
		},
		po2mo: {
		    files: {
		        src: 'languages/*.po',
		        expand: true,
		    },
		},
		pot: {
		      options:{
		          text_domain: 'bu-navigation',
		          dest: 'languages/',
		          keywords: [ // WordPress localisation functions
		            '__:1',
		            '_e:1',
		            '_x:1,2c',
		            'esc_html__:1',
		            'esc_html_e:1',
		            'esc_html_x:1,2c',
		            'esc_attr__:1', 
		            'esc_attr_e:1', 
		            'esc_attr_x:1,2c', 
		            '_ex:1,2c',
		            '_n:1,2', 
		            '_nx:1,2,4c',
		            '_n_noop:1,2',
		            '_nx_noop:1,2,3c'
		           ],
		      },
		      files:{
		          src:  [ '**/*.php' ],
		          expand: true,
		      }
		},
	});

	// Build task.
	grunt.registerTask('build', ['uglify']);

	// Default task.
	grunt.registerTask('default', ['build']);
}