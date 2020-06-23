'use strict';

var LIVE_RELOAD_PORT = 35729;
var lrSnippet = require('connect-livereload')({port: LIVE_RELOAD_PORT});
var rewriteRulesSnippet = require('grunt-connect-rewrite/lib/utils').rewriteRequest;
var gateway = require('gateway');

var mountFolder = function (connect, dir) {
	return connect.static(require('path').resolve(dir));
};

var mountPHP = function (dir, options) {
	return gateway(require('path').resolve(dir), options);
};

module.exports = function (grunt) {

	// Load grunt tasks
	require('matchdep').filterDev('grunt-*').forEach(grunt.loadNpmTasks);

	var iniConfig = require('ini').parse(require('fs')
			.readFileSync('./src/conf/config.ini', 'utf-8'));

	var rewrites = {};
	// BaseMap rewrites
	rewrites['^' + iniConfig.MOUNT_PATH +
			'/tiles/([^/]+)/([^/]+)/([^/]+)/([^/\\.]+)\\.(jpg|png)$'] =
			'/getTileImage.php?layer=$1&zoom=$2&x=$3&y=$4&ext=$5';
	rewrites['^' + iniConfig.MOUNT_PATH +
			'/tiles/([^/]+)/([^/]+)/([^/]+)/([^/]+)\\.grid\\.json(\\?callback=(.*))?$'] =
			'/getTileGrid.php?layer=$1&zoom=$2&x=$3&y=$4&callback=$6';

	// App configuration, used throughout
	var appConfig = {
		src: 'src',
		dist: 'dist',
		test: 'test',
		tmp: '.tmp'
	};

	grunt.initConfig({
		app: appConfig,
		watch: {
			livereload: {
				options: {
					livereload: LIVE_RELOAD_PORT
				},
				files: [
					'<%= app.src %>/htdocs/**/*.php',
					'<%= app.src %>/htdocs/img/**/*.{png,jpg,jpeg,gif}',
				]
			},
			gruntfile: {
				files: ['Gruntfile.js'],
				tasks: ['jshint:gruntfile']
			}
		},
		concurrent: {
			dist: [
				'htmlmin:dist',
				'copy'
			]
		},
		connect: {
			options: {
				hostname: 'localhost'
			},
			rules: rewrites,
			dev: {
				options: {
					base: '<%= app.src %>/htdocs',
					port: 8080,
					middleware: function (connect, options) {
						return [
							lrSnippet,
							rewriteRulesSnippet,
							mountFolder(connect, '.tmp'),
							mountFolder(connect, 'node_modules'),
							mountPHP(options.base),
							mountFolder(connect, options.base)
						];
					}
				}
			},
			dist: {
				options: {
					base: '<%= app.dist %>/htdocs',
					port: 8081,
					keepalive: true,
					middleware: function (connect, options) {
						return [
							mountPHP(options.base),
							mountFolder(connect, options.base),
							rewriteRulesSnippet
						];
					}
				}
			},
			test: {
				options: {
					base: '<%= app.test %>',
					port: 8000,
					middleware: function (connect, options) {
						return [
							rewriteRulesSnippet,
							mountFolder(connect, '.tmp'),
							mountFolder(connect, 'node_modules'),
							mountFolder(connect, options.base),
							mountFolder(connect, appConfig.src + '/htdocs/js'),
							mountPHP(appConfig.src + '/htdocs')
						];
					}
				}
			}
		},
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			gruntfile: ['Gruntfile.js']
		},
		htmlmin: {
			dist: {
				options: {
					collapseWhitespace: true
				},
				files: [{
					expand: true,
					cwd: '<%= app.src %>',
					src: '**/*.html',
					dest: '<%= app.dist %>'
				}]
			}
		},
		copy: {
			app: {
				expand: true,
				cwd: '<%= app.src %>/htdocs',
				dest: '<%= app.dist %>/htdocs',
				src: [
					'**/*.{png,gif,jpg,jpeg}',
					'**/*.php'
				]
			},
			conf: {
				expand: true,
				cwd: '<%= app.src %>/conf',
				dest: '<%= app.dist%>/conf',
				src: [
					'**/*',
					'!**/*.orig'
				]
			},
			lib: {
				expand: true,
				cwd: '<%= app.src %>/lib',
				dest: '<%= app.dist %>/lib',
				src: [
					'**/*'
				],
				options: {
					mode: true
				}
			}
		},
		replace: {
			dist: {
				src: [
					'<%= app.dist %>/htdocs/index.html',
					'<%= app.dist %>/**/*.php'
				],
				overwrite: true,
				replacements: [
					{
						from: 'requirejs/require.js',
						to: 'lib/requirejs/require.js'
					}
				]
			}
		},
		open: {
			dev: {
				path: 'http://localhost:<%= connect.dev.options.port %>'
			},
			test: {
				path: 'http://localhost:<%= connect.test.options.port %>/basemap.html'
			},
			dist: {
				path: 'http://localhost:<%= connect.dist.options.port %>'
			}
		},
		clean: {
			dist: ['<%= app.dist %>'],
			dev: ['<%= app.tmp %>', '.sass-cache']
		}
	});

	grunt.event.on('watch', function (action, filepath) {
		// Only lint the file that actually changed
		grunt.config(['jshint', 'scripts'], filepath);
	});

	grunt.registerTask('test', [
		'clean:dist',
		'connect:test'
	]);

	grunt.registerTask('build', [
		'clean:dist',
		'concurrent:dist',
		'replace',
		'connect:test',
		'open:test',
		'connect:dist'
	]);

	grunt.registerTask('default', [
		'clean:dist',
		'configureRewriteRules',
		'connect:test',
		'connect:dev',
		'open:test',
		'open:dev',
		'watch'
	]);

};
