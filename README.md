# Jorge

Jorge is an experimental command-line tool for managing the complex interaction of Drush, Git, Lando, and Terminus to automate common tasks.

**Note:** Jorge stores no connection information about the various services and does not provide any additional permission; your account must already have access (ideally via SSH key or token) to the various projects on GitHub and Pantheon, and Jorge works on your behalf using those credentials.

## Installation

### Install with Git

1. In your Projects directory, `git clone` this repo.

2. `cd jorge` (or whatever other directory you cloned into)

3. `composer install`

4. Get the full path to your Jorge directory with `pwd`

5. `sudo ln -s {that-full-path}/bin/jorge /usr/local/bin/jorge` (you may need to enter your workstation password).

That's it. The command `jorge` should now exist and give you a list of things it can do.

### Install with Composer

This way isn’t implemented yet.

## Configuration

Jorge has no global configuration; it works on the project level only.

The project’s root directory should contain a folder `.jorge`, which should have a file `config.yml`. Samples are included in Jorge’s own `.jorge` directory.

In `.jorge/config.yml`, you **must** have the key `appType`. Currently, only `drupal8` and `jorge` are recognized as values.

Optionally, you may also have the key `include_config`, which specifies a list of additional configuration files to include. Values in those files will override any settings in the main `config.yml`.

### Drupal 8 Configuration

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

### `jorge reset`

Sets up the local development environment as specified in the configuration file(s) described above.

Your project must be in a clean state: if Git can’t change branches, the whole thing will fail.

Optionally takes command-line switches which will override those settings (except `rsync`); see `jorge help reset` for details.

If a username is provided but no password is supplied, Jorge will prompt you for one. If you leave that blank also, the password will not be reset.

### _Not Implemented Yet_

**`jorge save`** – Save the current state of the code, database, and files so that it can be restored by a later `reset`.


## Future Work

- Install with Composer

- Handle the various failure conditions of the `reset` steps

- Tests (see [Testing Commands](https://symfony.com/doc/current/console.html#testing-commands) for example)

- Better awareness of initial/current state: use APIs for Git, Lando, &c., instead of commands

- Option to stash or discard changes before a `git checkout`?

- Replace `system()` and `exec()` calls with Symfony Process component (currently it gets tangled when Lando needs to `attach` to the Docker container)

- Implement `jorge save` and refactor `jorge reset` to be able to use saved state
