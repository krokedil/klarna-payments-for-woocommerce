Docker

To initiate the project start with the command "docker-compose up -d".
This will setup the images and install WooCommerce and Storefront as well as changing some settings. This might take a minute or two the first time.
After the installation is done, you can find the plugin files in the Root folder, and the WordPress files if they are needed under the wp folder.
This will let you access and change other plugins as needed for potential compatibility debugging.
After the initial setup you can close the project with "docker-compose stop" and start it with "docker-compose start". The WordPress installation will be available under your localhost.

Commands
Install the containers:
"docker-compose up -d"

Start the containers
"docker-compose start"

Stop the containers
"docker-compose stop"