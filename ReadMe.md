
# The one and only Neos9 workshop 2026 distribution
 
For further information about the workshop see: https://www.neoscon.io/all-you-need-to-know/mastering-neos-9.html

As promised we prepared a distribution for the workshop. This contains a Neos 9 with Neos.Demo and two additional packages we will be working on.

### Setup

For setup, we recommend using DDEV for that the .ddev/config.yaml is already in place.
Though you can also use a manual setup to your liking (PHP 8.3 with MariaDB ~11) is recommended.
https://docs.neos.io/guide/installation-development-setup/manual-setup/mac-os-linux-using-the-embedded-web-server

```sh
composer install
```

After installation with composer user and the demo site need to be imported, but as usual "./flow setup" will help you with that.

```sh
./flow doctrine:migrate
./flow user:create --roles Administrator admin admin Jon Doe
./flow cr:setup
./flow site:importall --package-key Neos.Demo
```

To validate that everything is running we recommend testing a few basic things:

1.) The Neos 9 backend (Ui) and frontend is accessible via the browser

Inside of `DistributionPackages/Neos.Demo` run via CLI

2.) `composer run lint:phpstan` to see that tooling works

3.) `composer run test:behavioral` to see that the behavioral via behat work

In case you're not using DDEV you have to provide a different database for testing, this can be inspected via:

```sh
FLOW_CONTEXT=Testing/Behat flow configuration:show --path Neos.Flow.persistence.backendOptions
```
