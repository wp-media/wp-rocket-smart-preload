# WP Rocket - Smart Preload

Analyzes your site's traffic and generates a sitemap with the most visited pages to be used in WP Rocket's Preload feature.

**Features:**

* Keeps track of visit to pages
* Generates a sitemap with a list of the most visited pages ordered from most visited to least visited (The sitemap is automatically passed to WP Rocket)
* Customizable list of pages that will always be in the sitemap (in addition to most visited ones). By default, the Home Page will always be in the list of the sitemap
* Same IP protection: Multiple visits from the same IP to the same page in a short period of time won't be recorded to prevent the manipulation by refreshing a page multiple times (This can be disabled. Useful for cases in which the real IP of the user cannot be gotten)
* Automatic tasks to update preload table (Requires a working cronjob)
* Automatic clean up tasks to prevent this plugin's database table from growing indefinitely (Requires a working cronjob)
* A lot of customizable parameters using filters

**Last tested with:**

* WP Rocket 3.18
* WordPress 6.x

## Contribution

### Installation of development environment

* Linux or MacOS required (On windows you can use WSL)
* Make sure you have NodeJS installed (v22.13.1 or later recommended) (You can use [nvm](https://github.com/nvm-sh/nvm) to manage different NodeJS versions)
* Make sure you have **zip** command available in your system (Used by the build script. Most Linux distributions come with the 'zip' command pre-installed. You can run ```zip``` in your terminal to see if you have it installed.)
* After cloning the repository for the first time, run the command ```npm install```, so, the dependencies are installed (Required to run the build script)

### Plugin's version

**IMPORTANT:** When you make changes to the plugin, you also need to update the version in the `package.json` file before pushing the final code of the new contribution to the repository:

* Only change the version in the `package.json` file.
* Do not touch any line related to the version in `wp-rocket-smart-preload.php` file. It is `0.0.0-dev` because it will be replaced later in the `build:release` process.
* We use the SEMVER v2.0.0 specification here, please check [https://semver.org/](https://semver.org/)

In summary given a version number MAJOR.MINOR.PATCH, increment the:

* **MAJOR** version when you make incompatible API changes
* **MINOR** version when you add functionality in a backward compatible manner
* **PATCH** version when you make backward compatible bug fixes

### Push to remote

Once you're done making changes, push the new branch to the repo, create a PR, and merge changes (resolve any conflicts if needed).

Pull the changes back to your local `main` branch. Doing things this way should allow your local repo to be completely up to date with changes anyone else may have made.

### Build the release and generate the zip file

**IMPORTANT:** If you are on Windows, `zip` command (Required for the build process) won't be available by defaul. You can use [WSL](https://learn.microsoft.com/es-es/windows/wsl/install)

Generate the plugin zip file with the following command in your terminal while in the directory of the project:
```npm run build:release```

If you have node 22.x.x+ installed, you can also use the following:
```node --run build:release```

This will process PHP files and will transpile **TypeScript** code to JavaScript code.

If successful, `wp-rocket-smart-preload-vx.x.x.zip` will be created in the release directory (x.x.x will be the version in package.json)

There will be an unzipped wp-rocket-smart-preload directory created in the release directory as well.

It's important to do this step after pushing your branch to the repo, merging, etc, because that ensures the generated zip file includes the most up-to-date code (including any changes others may have pushed).

### Create the tag for the new release

Next run the following commands to push a version of the plugin to the repo under the related tag using the local branch you currently have selected (use the actual version number):
`git tag vx.x.x`

`git push origin tag vx.x.x`

### Create a New Release

Create a [new release](https://github.com/wp-media/wp-rocket-smart-preload/releases) in GitHub using the new tag you created.

* Select the [related tag](https://imagizer.imageshack.com/a/img923/6923/i4UgM6.png)
* Make the `Release title` the same as the tag (v2.1.0, for example)
* Include a description about the changes applied in the update
* Attach the new zip file so it can be downloaded as part of the release
