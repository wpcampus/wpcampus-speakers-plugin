const gulp = require('gulp');
const notify = require('gulp-notify');
const shell = require('gulp-shell');

// Define the source paths for each file type.
const src = {
	php: ['**/*.php','!vendor/**','!node_modules/**']
};

// "Sniff" our PHP.
gulp.task('php', function() {
	// TODO: Clean up. Want to run command and show notify for sniff errors.
	return gulp.src('wpcampus-speakers.php', {read: false})
		.pipe(shell(['composer sniff'], {
			ignoreErrors: true,
			verbose: false
		}))
		.pipe(notify('WPC Speakers PHP sniffed'), {
			onLast: true,
			emitError: true
		});
});

// Test our files.
gulp.task('test',['php']);

// I've got my eyes on you(r file changes).
gulp.task('watch',function() {
	gulp.watch(src.php,['php']);
});

// Let's get this party started.
gulp.task('default',['test']);
