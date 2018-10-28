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
var wpPot = require('gulp-wp-pot');
var zip = require('gulp-zip');

/**
 * Local variables
 */
var prefix = 'wcsdm-';

var scriptsSrcDir = 'assets/src/js/';
var scriptsDestDir = 'assets/js/';

var stylesSrcDir = 'assets/src/scss/';
var stylesDestDir = 'assets/css/';

var assets = [
    {
        location: 'backend',
        scripts: ['helpers.js', 'map-picker.js', 'table-rates.js', 'backend.js'],
        styles: ['backend.scss'],
    },
    {
        location: 'frontend',
        scripts: ['helpers.js', 'frontend.js'],
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
var scriptsHandler = function (asset, isMinify) {
    var srcParam = asset.scripts.map(function (file) {
        return scriptsSrcDir + file;
    });

    return gulp.src(srcParam)
        .pipe(errorHandler())
        .pipe(concat(asset.location + '.js'))
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
var stylesHandler = function (asset, isMinify) {
    var srcParam = asset.styles.map(function (file) {
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

assets.forEach(function (asset) {
    /**
     * Minify Scripts Task
     */
    var scriptsTaskName = asset.location + '-scripts-minify';

    gulp.task(scriptsTaskName, function () {
        return scriptsHandler(asset, true);
    });

    tasksListBuild.push(scriptsTaskName);

    /**
     * Styles Task
     */
    var stylesTaskName = asset.location + '-styles-minify';

    gulp.task(stylesTaskName, function () {
        return stylesHandler(asset, true);
    });

    tasksListBuild.push(stylesTaskName);
});
gulp.task('build', tasksListBuild, function () {
    return gulp.src(['./*.php', './**/*.php'])
        .pipe(wpPot({
            'domain': 'wcsdm',
            'package': 'WooCommerce-Shipping-Distance-Matrix'
        }))
        .pipe(gulp.dest('languages/wcsdm.pot'));
});

/**
 * Default tasks
 */
var tasksListDefault = [];

assets.forEach(function (asset) {
    /**
     * Scripts Task
     */
    var scriptsTaskName = asset.location + '-scripts';

    gulp.task(scriptsTaskName, function () {
        return scriptsHandler(asset, false);
    });

    tasksListDefault.push(scriptsTaskName);

    /**
     * Styles Task
     */
    var stylesTaskName = asset.location + '-styles';

    gulp.task(stylesTaskName, function () {
        return stylesHandler(asset, false);
    });

    tasksListDefault.push(stylesTaskName);
});

gulp.task('default', tasksListDefault, function () {
    if (argv.hasOwnProperty('proxy')) {
        browserSync.init({
            proxy: argv.proxy
        });
    }

    assets.forEach(function (asset) {
        var watchScriptsSrc = asset.scripts.map(function (script) {
            return scriptsSrcDir + script;
        });
        gulp.watch(watchScriptsSrc, [asset.location + '-scripts']).on('change', browserSync.reload);

        gulp.watch(asset.styles, [asset.location + '-styles']);
    });
});

// Export task
gulp.task('export', ['build'], function () {
    gulp.src(['./**', '!dist/', '!dist/**', '!node_modules/', '!node_modules/**', '!assets/src/', '!assets/src/**', '!gulpfile.js', '!package-lock.json', '!package.json'])
        .pipe(zip('wcsdm.zip'))
        .pipe(gulp.dest('dist'));
});