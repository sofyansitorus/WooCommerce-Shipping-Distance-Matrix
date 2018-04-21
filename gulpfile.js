var gulp = require('gulp');
var rename = require('gulp-rename');
var uglify = require('gulp-uglify');
var iife = require('gulp-iife');
var sass = require('gulp-sass');
var cleanCSS = require('gulp-clean-css');
var plumber = require('gulp-plumber');
var notify = require('gulp-notify');

var scriptsSrc = ['assets/src/js/*.js'];
var scriptsDest = 'assets/js';

var minifyScriptsSrc = ['assets/js/*.js', '!assets/js/*.min.js'];
var minifyScriptsDest = 'assets/js';

var sassSrc = ['assets/src/scss/*.scss'];
var sassDest = 'assets/css';

var minifyCssSrc = ['assets/css/*.css', '!assets/css/*.min.css'];
var minifyCssDest = 'assets/css';

// Custom error handler
var errorHandler = function () {
    return plumber(function (err) {
        notify.onError({
            title: 'Gulp error in ' + err.plugin,
            message: err.toString()
        })(err);
    });
};

// Scripts
gulp.task('scripts', function () {
    return gulp.src(scriptsSrc)
        .pipe(errorHandler())
        .pipe(iife({
            useStrict: true,
            trimCode: true,
            prependSemicolon: true,
            params: ['$'],
            args: ['jQuery']
        }))
        .pipe(gulp.dest(scriptsDest));
});

// Minify scripts
gulp.task('minify-scripts', function () {
    return gulp.src(minifyScriptsSrc)
        .pipe(errorHandler())
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(uglify())
        .pipe(gulp.dest(minifyScriptsDest));
});

// SASS
gulp.task('sass', function () {
    return gulp.src(sassSrc)
        .pipe(errorHandler())
        .pipe(sass().on('error', sass.logError))
        .pipe(gulp.dest(sassDest));
});

// Minify CSS
gulp.task('minify-css', () => {
    return gulp.src(minifyCssSrc)
        .pipe(errorHandler())
        .pipe(rename({
            suffix: '.min'
        }))
        .pipe(cleanCSS({ compatibility: 'ie8' }))
        .pipe(gulp.dest(minifyCssDest));
});

// Default task
gulp.task('default', ['scripts', 'minify-scripts']);

// Dev task with watch
gulp.task('watch', ['scripts', 'minify-scripts', 'sass', 'minify-css'], function () {
    gulp.watch([scriptsSrc], ['scripts']);
    gulp.watch([minifyScriptsSrc], ['minify-scripts']);
    gulp.watch([sassSrc], ['sass']);
    gulp.watch([minifyCssSrc], ['minify-css']);
});