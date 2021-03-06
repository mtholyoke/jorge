<?php

namespace MountHolyoke\Jorge\Tool;

use MountHolyoke\Jorge\Tool\Tool;
use Psr\Log\LogLevel;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides a Jorge tool that can execute Lando commands.
 *
 * @link https://github.com/mtholyoke/jorge
 *
 * @author Jason Proctor <jproctor@mtholyoke.edu>
 * @copyright 2018 Trustees of Mount Holyoke College
 */
class LandoTool extends Tool {
  /** @var array $version Lando version, parsed */
  protected $version;

  /**
   * Adds the appropriate verbosity option.
   *
   * @param string $argv Lando arguments just before execution
   * @return string
   */
  protected function applyVerbosity($argv = '') {
    $verbosityMap = [
      OutputInterface::VERBOSITY_QUIET        => '2>&1',
      OutputInterface::VERBOSITY_NORMAL       => '',
      OutputInterface::VERBOSITY_VERBOSE      => '-v',
      OutputInterface::VERBOSITY_VERY_VERBOSE => '-vv',
      OutputInterface::VERBOSITY_DEBUG        => '-vvvv',
    ];

    if (array_key_exists($this->verbosity, $verbosityMap)) {
      return trim($argv . ' ' . $verbosityMap[$this->verbosity]);
    }
    return $argv;
  }

  /**
   * Establishes the `lando` tool.
   */
  protected function configure() {
    $this->setName('lando');
  }

  /**
   * Gets the Drush version.
   *
   * TODO: This really belongs in the Drush command but it was easier here.
   */
  public function getDrushVersion() {
    $exec = $this->exec('drush version');
    if ($exec['status'] != 0 || !array_key_exists('output', $exec) || count($exec['output']) == 0) {
      $this->log(LogLevel::ERROR, 'Unable to determine Drush version');
      $this->disable();
      return NULL;
    }
    preg_match('/Drush version : (\d+)\./', $exec['output'][0], $matches);
    return $matches[1];
  }

  /**
   * Gets the version of Lando installed so we can parse its output.
   *
   * @return array|null
   */
  public function getVersion() {
    if (isset($this->version)) {
      return $this->version;
    }

    $exec = $this->exec('version');
    if ($exec['status'] != 0 || !array_key_exists('output', $exec) || count($exec['output']) == 0) {
      $this->log(LogLevel::ERROR, 'Unable to determine version');
      $this->disable();
      return NULL;
    }
    # Version string is in the last line, even if there’s an upgrade warning.
    $raw = $exec['output'][count($exec['output']) - 1];

    $regex = preg_match('/^v(\d+)\.(\d+)\.(\d+)(.*)$/', $raw, $matches);
    if (!$regex) {
      $this->log(LogLevel::ERROR, 'Unable to parse version');
      $this->disable();
      return NULL;
    }
    if ($matches[1] != 3) {
      $this->log(
        LogLevel::WARNING,
        'Unrecognized Lando version %v; some functions may not work.',
        ['%v' => $raw]
      );
    }
    $this->version = [
      'raw'   => $raw,
      'major' => $matches[1],
      'minor' => $matches[2],
      'patch' => $matches[3],
      'functions' => [
        'auth' => TRUE,
        'list' => 4,
      ],
    ];
    $suffix = $matches[4];
    if (preg_match('/^-(alpha|beta|rc|aft|rrc)\.(\d+)$/', $suffix, $matches)) {
      $p = $this->version['prerelease'] = $matches[1];
      $i = $this->version['iteration']  = $matches[2];

      if (substr($raw, 0, 7) == 'v3.0.0-') {
        switch ($p) {
          case 'alpha':
            $this->version['functions']['auth'] = FALSE;
            $this->version['functions']['list'] = 0;
            break;
          case 'beta':
            $this->version['functions']['auth'] = FALSE;
            $this->version['functions']['list'] = ($i < 37) ? 0 : 1;
            break;
          case 'rc':
            if ($i == 1) {
              $this->version['functions']['auth'] = FALSE;
              $this->version['functions']['list'] = 1;
            } else {
              $this->version['functions']['list'] = ($i < 13) ? 2 : 3;
            }
            break;
          # Newest behavior is default:
          // case 'aft':
          // case 'rrc':
          //   $this->version['functions']['list'] = 4;
        }
      }
    } elseif (!empty($suffix)) {
      $this->version['suffix'] = $suffix;
      $this->log(
        LogLevel::WARNING,
        'Unrecognized Lando version suffix %s in %v; some functions may not work.',
        ['%s' => $suffix, '%v' => $raw]
      );
    }
    return $this->version;
  }

  /**
   * Reads the Lando config file, and enables the tool if config is present.
   */
  protected function initialize() {
    $this->enable();

    if (empty($this->getExecutable())) {
      $this->disable();
      return;
    }

    if (($rootPath = $this->jorge->getPath()) === NULL) {
      $this->disable();
      return;
    }

    # Fail silently if the current project doesn’t use Lando.
    $this->config = $this->jorge->loadConfigFile('.lando.yml', NULL);
    if (empty($this->config)) {
      $this->disable();
    }
  }

  /**
   * Checks the version to see if an auth token is required to pull.
   *
   * TODO: check for TERMINUS_TOKEN in environment.
   *
   * @return boolean
   */
  public function needsAuth() {
    $v = $this->getVersion();
    return $v['functions']['auth'];
  }

  /**
   * Parse the output from `lando list`, which is not quite JSON.
   *
   * @param array $lines Raw output from `lando list`
   * @return array
   */
  protected function parseLandoList(array $lines = []) {
    # Skip over Lando complaining about updates.
    while (!empty($lines) && substr($lines[0], 0, 1) != '[' && $lines[0] != '{') {
      array_shift($lines);
    }

    if (empty($lines)) {
      return [(object) [
        'name' => '*',
        'running' => FALSE,
      ]];
    }

    $v = $this->getVersion();
    switch ($v['functions']['list']) {
      case 0:
        # Versions before v3.0.0-beta.37 return a series of {}s. Make a list.
        for ($i = 0; $i < (count($lines) - 1); $i++) {
          # Append a comma to every line except the last.
          if ($lines[$i] == '}') {
            $lines[$i] .= ', ';
          }
        }
        return json_decode('[' . implode('', $lines) . ']');
      case 1:
        # v3.0.0-beta.37 to v3.0.0-rc.1 return a valid list of {}s.
        return json_decode(implode('', $lines));
      case 2:
        # v3.0.0-rc.2 to v3.0.0-rc.12 return a {} with keys as the names
        # of the Lando projects, and values a list of {}s similar to the
        # output of `lando info`. As far as I know, 2019-02-15, the presence
        # of a key is sufficient to assume it is running, so we make it look
        # enough like the others to pass the tests. Note that whenever Lando
        # is running, even if no projects are, there is a "_global_" key.
        foreach ($lines as &$line) {
          # Until Lando PR #1457 is merged we don’t get good json.
          if (preg_match('/^\s*(\w+):(.*)$/', $line, $matches)) {
            $line = ' "' . $matches[1] . '":' . str_replace("'", '"', $matches[2]);
          } elseif (preg_match("/^(.*?)'(.*)'(.*)$/", $line, $matches)) {
            $line = $matches[1] . '"' . $matches[2] . '"' . $matches[3];
          }
          $line = preg_replace('#\\x1b[[][^A-Za-z]*[A-Za-z]#', '', $line);
        }
        # ... and continue into case 3:
      case 3:
        # v3.0.0=rc.13 to v3.0.0-rc.23 have the same structure as above,
        # but can format the output as valid JSON to start with.
        $json = json_decode(implode(' ', $lines));
        $list = [];
        foreach ($json as $name => $info) {
          $list[] = (object) [
            'name'    => $name,
            'running' => TRUE,
            'info'    => $info,
          ];
        }
        return $list;
    }

    # v3.0.0-aft.1 to current (v.3.0.0-rrc.2 as of 2020-03-28) return a []
    # of the inner {} parts of the rc.2 structure, concatenated. They can be
    # associated with a project by the contents of the "app" key. There may
    # be more than one item per project. Whenever Lando is running, even if
    # no projects are, there is a "_global_" app.
    $json = json_decode(implode(' ', $lines));
    $list = [];
    if (empty($json)) {
      return $list;
    }
    foreach ($json as $item) {
      if (array_key_exists($item->app, $list)) {
        $list[$item->app]->info[] = $item;
        continue;
      }
      $list[$item->app] = (object) [
        'name'    => $item->app,
        'running' => TRUE,
        'info'    => [$item],
      ];
    }
    return array_values($list);
  }

  /**
   * Ensures that Lando is started in the current project.
   */
  public function requireStarted() {
    $status = $this->getStatus(TRUE);
    if ($this->isEnabled() && (is_null($status) || !$status->running)) {
      $this->run('start');
      $this->updateStatus();
    }
  }

  /**
   * Computes and saves a status.
   *
   * Calls `lando list`, parses the results, and then identifies if
   * any of the results match the Lando environment we’re working in.
   *
   * @param string $name The name of the Lando environment
   */
  public function updateStatus($name = '') {
    if (empty($name)) {
      if ($this->isEnabled()) {
        $name = $this->config['name'];
      } else {
        $this->log(
          LogLevel::WARNING,
          'No Lando environment configured or specified'
        );
        return;
      }
    }

    $v = $this->getVersion();
    $list_command = 'list';
    if ($v['functions']['list'] >= 3) {
      # Switch introduced in v3.0.0-rc.13
      $list_command .= ' --format json';
    }
    $exec = $this->exec($list_command);
    if ($exec['status'] != 0) {
      $this->log(LogLevel::ERROR, 'Unable to determine status');
      $this->disable();
      return;
    }

    $list = $this->parseLandoList($exec['output']);
    $set = FALSE;
    foreach ($list as $status) {
      if ($status->name == $name) {
        $this->setStatus($status);
        $set = TRUE;
      }
    }
    if (!$set) {
      $this->log(
        LogLevel::WARNING,
        'Unable to determine status for Lando environment "{%name}"',
        ['%name' => $name]
      );
    }
  }
}
