# Jorge

Jorge is an experimental command-line tool for managing the complex interaction of Drush, Git, Lando, and Terminus to automate common tasks.

**Note:** Jorge stores no connection information about the various services and does not provide any additional permission; your account must already have access (ideally via SSH key or token) to the various projects on GitHub and Pantheon, and Jorge works on your behalf using those credentials.

## Installation

### Recommended: Install with Composer/CGR

This is not a thing to `require` within a project; it’s a tool for your workstation. Composer facilitates that with `composer global require`, but that command is somewhat flawed in the way it manages things, so we prefer [`cgr`](https://pantheon.io/blog/fixing-composer-global-command).

To install `cgr`, run `composer global require consolidation/cgr` and it will be added in the `.composer` directory off your home directory. You will also need to tell the system that you’re adding executables to a new place. In your home directory, there can be a file named `.bash_profile` or `.profile` which is run every time you log in. Edit that file (create one if neither exists) and add a line at the end:
```bash
export PATH="$PATH:~/.composer/vendor/bin"
```

In order for the new `PATH` to take effect, you can either log out and back in, or run `source .bash_profile` (or `source .profile`). You can test that it worked by running `cgr --help`; it should give you help instead of an error message.

After all that, you should be able to run `cgr mtholyoke/jorge`. If it is successfully installed, `jorge --version` will report “Can’t find project root” and a version.


### For Development: Install with Git

You can also clone this repo for development and run Jorge directly from that copy. Rather than adding `~/Projects/jorge/bin` (or whatever your Projects directory is; run `pwd` to check) to your path, I recommend making a symlink to `bin/jorge` (the actual program) from `/usr/local/bin` or some other location already in your path:
```bash
ln -s ~/Projects/jorge/bin/jorge /usr/local/bin/jorge
```

If you're going to do any development, also run `bin/setup.sh` once to install the standard Git hooks.

## Configuration

Jorge has no global configuration; it works on the project level only.

The project’s root directory should contain a subdirectory `.jorge`, which should have a file `config.yml`. Samples are included in Jorge’s own `.jorge` directory.

In `.jorge/config.yml`, you **must** have the key `appType`. Currently, only `drupal7`, `drupal8`, and `jorge` are recognized as values.

Optionally, you may also have the key `include_config`, which specifies a list of additional configuration files to include from the `.jorge` directory, to be loaded in the order specified. Values in those files will override any previously loaded settings, including the main `config.yml`. Note that `include_config` is only allowed in the main `config.yml` file.

### Drupal 7 or 8 Configuration

A config file may include the key `reset`, which contains a block that provides any of five optional parameters to the `reset` command (described below):
- `branch  ` - Which Git branch to reset your current codebase to (default `master`)
- `database` - Which Pantheon environment to copy the database from (default `dev`)
- `files   ` - Which Pantheon environment to copy the files from (default `dev`)
- `rsync   ` - Whether Lando should use `rsync` to copy files (default `TRUE`)
- `username` - A username (usually an admin) that needs a local password (no default)
- `password` - A local password for the username specified above (no default)

Commonly, the configuration committed to the project’s Git repo looks like this:
```yml
appType: drupal8
reset:
  branch: master
  database: dev
  files: dev
  rsync: TRUE
include_config:
  - local.yml
```

In `local.yml`, which is _not_ committed to the project’s Git repo:
```yml
reset:
  username: admin
  password: asdf1234
```


## Commands

### `jorge drush `_`{drush-command}`_

In a Composer-powered Drupal 8 project, `lando drush `_`{drush-command}`_ needs to be run inside the `web` directory (so it has access to Drupal), regardless of whether you’re currently in that directory. This command runs it from outside that directory, (after starting Lando if it’s not already running).

Accepts the `-y`/`--yes` and `-n`/`--no` option natively, but other Drush options need to be escaped. See `jorge help drush` for details. Note that `-n` is actually Symfony `--no-interaction`, which has approximately the same effect.


### `jorge reset`

Sets up the local development environment as specified in the configuration file(s) described above.

Starts Lando if it is not already running.

Your project must be in a clean state: if Git can’t change branches, the whole thing will fail.

Optionally takes command-line switches which will override those settings (except `rsync`); see `jorge help reset` for details.

If a username is provided but no password is supplied, Jorge will prompt you for one. If you leave that blank also, the password will not be reset.


### _Not Implemented Yet_

**`jorge save`** – Save the current state of the code, database, and files so that it can be restored by a later `reset`.


## Future Work

- Tests (see [Testing Commands](https://symfony.com/doc/current/console.html#testing-commands) for example)

- Implement [Tools](src/Tool/) for Git, Lando, &c., using APIs if possible, for better awareness of initial/current state

- Refactor the execution to take advantage of the implemented tools

- Option to stash or discard changes before a `git checkout`?

- Replace `system()` and `exec()` calls with Symfony Process component (currently it gets tangled when Lando needs to `attach` to the Docker container)

- Implement `jorge save` and refactor `jorge reset` to be able to use saved state
