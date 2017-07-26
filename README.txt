Datix Technical Test

Requirements:

	You'll need a computer with php (5.3.3 or greater) installed.
	You can use any editor/IDE you want and any operating system.

	There's no time limit.

	If you're not familiar with phpunit test mocks, check their
	website: https://phpunit.de/manual/current/en/test-doubles.html

	If you prefer some other mocking library (ie: mockery or prophecy), 
	feel free to include and use them.
	
	The source code is in the folder src/ and unit tests (phpunit)
	are in tests/

Before you start:

	from your console, in the root folder, run:

	    composer.phar install

	to install phpunit

What's expected:

	- Some tests are failing, find what's wrong and fix it
	- Refactor the existing procedural code into object oriented code
	- You'll need to refactor the tests too to match the new code
	- Make sure the tests pass
	- Add docblocks
	- Improve code style 

How to run the tests:

	from your console, in the root folder, run:

	    vendor/bin/phpunit tests

Extra points:

	- Make sure the functions don't silently fail, add some error handling
	- Extra points for following SOLID principles
	- Use value objects
	- Add logging 
	- Add different types of encryption

After you're done, please create a zip file of the DatixTest
folder and send it back to us (please *don't* include the
vendor/ directory because gmail will block it).

You can add any other suggestions you might have to the email.
