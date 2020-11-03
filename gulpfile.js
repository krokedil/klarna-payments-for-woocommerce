/* globals require */
var gulp = require('gulp');
var watch = require('gulp-watch');
var sort = require('gulp-sort');
var wpPot = require('gulp-wp-pot');

var translateFiles = '**/*.php';

gulp.task('makePOT', function () {
	return gulp.src('**/*.php')
		.pipe(sort())
		.pipe(wpPot({
			domain: 'klarna-payments-for-woocommerce',
			destFile: 'languages/klarna-payments-for-woocommerce.pot',
			package: 'klarna-payments-for-woocommerce',
			bugReport: 'http://krokedil.se',
			lastTranslator: 'Krokedil <info@krokedil.se>',
			team: 'Krokedil <info@krokedil.se>'
		}))
		.pipe(gulp.dest('languages/klarna-payments-for-woocommerce.pot'));
});

function makePot() {
	return gulp.src('**/*.php')
    .pipe(sort())	
    .pipe(wpPot({
        domain: 'klarna-payments-for-woocommerce',
        destFile: 'languages/klarna-payments-for-woocommerce.pot',
        package: 'klarna-payments-for-woocommerce',
        bugReport: 'http://krokedil.se',
        lastTranslator: 'Krokedil <info@krokedil.se>',
        team: 'Krokedil <info@krokedil.se>'
    }))
    .pipe(gulp.dest('languages/klarna-payments-for-woocommerce.pot'));
}

gulp.task('watch', function() {
    gulp.watch(translateFiles, makePot);
});