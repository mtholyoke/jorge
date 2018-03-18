# Jorge Tools

Tools are the components (Lando, Git, &c.) that we use to administer development environments. Ultimately, the goal is to be able to specify complex commands, such as `jorge reset`, as a series of instructions to tools.

## Creating a Tool

The base class, `MountHolyoke\Jorge\Tool\Tool`, provides common functionality like `Symfony\Component\Console\Command\Command` that can be overridden if necessary.

```php
namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;

class SampleTool extends Tool {
...
}
```

When a tool is instantiated, its `configure()` method is called. If the application does not provide a name for the tool, then `configure()` _must_ call `$this->setName()`. Note: the environment is available to `configure()` for establishing any default configuration, but the application (including its deduced project root, &c.) is not available yet.

```php
protected function configure() {
  $this->setName('sample');
}
```

When the tool is added to the application, the application can provide an executable or the tool will attempt to deduce it from its name. After this, its `initialize()` method is called, for any application-specific configuration. This is usually the first good opportunity to determine whether the tool should be enabled.

```php
protected function initialize() {
  if (!empty($this->getExecutable())) {
    $this->enable();
  }
}
```

`isEnabled()` indicates whether the tool is properly configured within the application to interact with the project. Not all functionality requires this: for example, a tool implementing `git` could be used for `git help` regardless of whether the application is being run in a Git repo, but `git pull` clearly requires that condition.

`getStatus()` runs `updateStatus()` if it has not previously been run (or if you call it with `TRUE` as the first argument) and returns the result of the most recent status update. `updateStatus()` defaults to `isEnabled()`, but can be overridden to provide something more useful to the specific tool.

```php
protected function updateStatus($args = NULL) {
  # Status is TRUE if we’re currently working in the project root.
  $cwd = getcwd();
  $root = $this->getApplication()->rootPath;
  $this->setStatus($cwd == $root);
}
```

`log()` passes a loggable message to the application’s logger. `use Psr\Log\LogLevel;` for named levels as constants.

### Basic Functionality

`exec('foo')` runs the command-line tool with `foo` as its argument string and returns an array of the results. It ignores the application’s verbosity setting, so is most useful for internal operations where you need information to make a decision about further actions.

`run('foo')` checks whether the tool is enabled, and if so, runs the command-line tool with `foo` as its argument string, dumps any output to the user, and returns only the status code.

`runThis('foo')` is like `run()` but skips the enablement check.

## Using a Tool

The Jorge application has an `addTool()` method which takes a new instance of the tool and optionally its command-line executable. Once it has been added, it is available to commands and other tools. A sample from `Jorge.php`:
```php
use MountHolyoke\Jorge\Tool\LandoTool;
// ...
public function configure() {
  // ...
  $this->addTool(new LandoTool());
}
```
... and its usage in `ResetCommand`:
```php
protected function executeDrupal8() {
  $jorge = $this->getApplication();
  $lando = $jorge->getTool('lando');
  // ...
  if (!$lando->getStatus()->running) {
    $lando->run('start');
  }
  // ...
}
```
