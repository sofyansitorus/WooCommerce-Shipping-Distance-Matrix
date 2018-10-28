/**
 * Import modules
 */
var gulp = require('gulp');
var rename = require('gulp-rename');
var uglify = require('gulp-uglify');
var iife = require('gulp-iife');
var concat = require('gulp-concat');
var sass = require('gulp-sass');
var autoprefixer = require('gulp-autoprefixer');
var plumber = require('gulp-plumber');
var notify = require('gulp-notify');
var browserSync = require('browser-sync').create();
var argv = require('yargs').argv;
var gulpif = require('gulp-if');
var cleanCSS = require('gulp-clean-css');
var sourcemaps = require('gulp-sourcemaps');

/**
 * Local variables
 */
var prefix = 'wcsdm-';

var scriptsSrcDir = 'assets/src/js/';
var scriptsDestDir = 'assets/js/';

var stylesSrcDir = 'assets/src/scss/';
var stylesDestDir = 'assets/css/';

var sources = [
    {
        key: 'backend',
        scripts: ['shared.js', 'map-picker.js', 'table-rates.js', 'backend.js'],
        styles: ['backend.scss'],
    },
    {
        key: 'frontend',
        scripts: ['shared.js', 'frontend.js'],
        styles: ['frontend.scss'],
    }
];

/**
 * Custom error handler
 */
var errorHandler = function () {
    return plumber(function (err) {
        notify.onError({
            title: 'Gulp error in ' + err.plugin,
            message: err.toString()
        })(err);
    });
};

/**
 * Script taks handler
 */
var scriptsHandler = function (source, isMinify) {
    var srcParam = source.scripts.map(function (file) {
        return scriptsSrcDir + file;
    });

    return gulp.src(srcParam)
        .pipe(errorHandler())
        .pipe(concat(source.key + '.js'))
        .pipe(iife({
            useStrict: true,
            trimCode: true,
            prependSemicolon: false,
            bindThis: false,
            params: ['$'],
            args: ['jQuery']
        }))
        .pipe(rename({
            prefix: prefix,
        }))
        .pipe(gulp.dest(scriptsDestDir))
        .pipe(gulpif(isMinify, rename({
            suffix: '.min',
        })))
        .pipe(gulpif(isMinify, sourcemaps.init()))
        .pipe(gulpif(isMinify, uglify()))
        .pipe(gulpif(isMinify, sourcemaps.write()))
        .pipe(gulpif(isMinify, gulp.dest(scriptsDestDir)));
}

/**
 * Style taks handler
 */
var stylesHandler = function (source, isMinify) {
    var srcParam = source.styles.map(function (file) {
        return stylesSrcDir + file;
    });

    return gulp.src(srcParam)
        .pipe(errorHandler())
        .pipe(gulpif(isMinify, sourcemaps.init()))
        .pipe(sass().on('error', sass.logError))
        .pipe(autoprefixer(
            'last 2 version',
            '> 1%',
            'safari 5',
            'ie 8',
            'ie 9',
            'opera 12.1',
            'ios 6',
            'android 4'))
        .pipe(rename({
            prefix: prefix,
        }))
        .pipe(gulp.dest(stylesDestDir))
        .pipe(gulpif(isMinify, rename({
            suffix: '.min',
        })))
        .pipe(gulpif(isMinify, cleanCSS({
            compatibility: 'ie8',
        })))
        .pipe(gulpif(isMinify, sourcemaps.write()))
        .pipe(gulpif(isMinify, gulp.dest(stylesDestDir)))
        .pipe(gulpif(!isMinify, browserSync.stream()));
}

/**
 * Build tasks
 */
var tasksListBuild = [];

sources.forEach(function (source) {
    /**
     * Minify Scripts Task
     */
    var scriptsTaskName = source.key + '-scripts-minify';

    gulp.task(scriptsTaskName, function () {
        return scriptsHandler(source, true);
    });

    tasksListBuild.push(scriptsTaskName);

    /**
     * Styles Task
     */
    var stylesTaskName = source.key + '-styles-minify';

    gulp.task(stylesTaskName, function () {
        return stylesHandler(source, true);
    });

    tasksListBuild.push(stylesTaskName);
});
gulp.task('build', tasksListBuild);

/**
 * Default tasks
 */
var tasksListDefault = [];

sources.forEach(function (source) {
    /**
     * Scripts Task
     */
    var scriptsTaskName = source.key + '-scripts';

    gulp.task(scriptsTaskName, function () {
        return scriptsHandler(source, false);
    });

    tasksListDefault.push(scriptsTaskName);

    /**
     * Styles Task
     */
    var stylesTaskName = source.key + '-styles';

    gulp.task(stylesTaskName, function () {
        return stylesHandler(source, false);
    });

    tasksListDefault.push(stylesTaskName);
});

gulp.task('default', tasksListDefault, function () {
    if (argv.hasOwnProperty('proxy')) {
        browserSync.init({
            proxy: argv.proxy
        });
    }

    sources.forEach(function (source) {
        var watchScriptsSrc = source.scripts.map(function (script) {
            return scriptsSrcDir + script;
        });
        gulp.watch(watchScriptsSrc, [source.key + '-scripts']).on('change', browserSync.reload);

        gulp.watch(source.styles, [source.key + '-styles']);
    });
});