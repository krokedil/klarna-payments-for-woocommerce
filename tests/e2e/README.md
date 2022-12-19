# E2E Tests with Playwright

#### Description
This plugin uses E2E testing to ensure functionality. For this we use Playwright to run our tests. These tests will be run automatically when a PR is created, but can also be triggered manually from Github actions using the Workflow Dispatch trigger, which will let you set some parameters that might be useful, such as WordPress and WooCommerce versions.

These tests can also be run manually in a local environment, either using a predefined site, or by spinning up a docker container for the tests to use.

If you want to run the tests against a local environment you will need to setup a .env file that sets the environment variables needed for the tests to run. The tests also require access to the API, since it will create the items it needs on the fly for the tests. You can find a full list of requirements bellow.

#### Requirements to run locally with provided docker container.
* Docker - [Link](https://www.docker.com/products/docker-desktop/)
* Node v16 or higher - [Link](https://nodejs.org/en/download/) <sup><sub>recommended to be installed using [NVM](https://github.com/nvm-sh/nvm)</sub></sup>

##### How to run with provided docker container.
1. Open the `tests/e2e` folder in your CLI. For VS Code you can rightclick the folder and press "Open in Integrated Ierminal".
2. Run `npm ci` to install required packages.
3. Run `npm run docker:up` to let our script settup the docker container.
4. Run `npm test` to run the normal tests, and `npm test:debug` to run the tests with headless mode turned off to be able to see the browser while the test is running.
5. When done with the testing, you can run npm `docker:down` to kill the docker container. Only to be run when you are done with all the testing.

#### Requirements to run locally with your own site.
* Node v16 or higher - [Link](https://nodejs.org/en/download/) <sup><sub>recommended to be installed using [NVM](https://github.com/nvm-sh/nvm)</sub></sup>
* A working site with the plugin installed.
* An account with admin access to the site you want to run the tests on.
* API keys that work, or using the plugin [WP API - Basic Auth](https://github.com/WP-API/Basic-Auth) which will let us use the admin login as the authorization for the API.
* A .env file with the required environment variables set. If they are missing the default values will be used instead.

##### How to run with your own site.
1. Ensure that you have a site setup and running using whatever method you want.
2. Create a `.env` in the tests folder file following the example in the [.example.env](./.example.env) file
3. Open the `tests/e2e` folder in your CLI. For VS Code you can rightclick the folder and press "Open in Integrated Ierminal".
4. Run `npm ci` to install required packages.
5. Run `npm test` to run the normal tests, and `npm test:debug` to run the tests with headless mode turned off to be able to see the browser while the test is running.

#### Usefull links
* [Playwright documentation](https://playwright.dev/docs/intro)
* [Krokedil WC Setup Package](https://krokedil.se)
