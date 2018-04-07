<?php
declare(strict_types = 1);

namespace MountHolyoke\JorgeTests\Tool;

use MountHolyoke\Jorge\Jorge;
use MountHolyoke\Jorge\Tool\Tool;
use MountHolyoke\JorgeTests\Mock\MockTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Exception\LogicException;

class ToolTest extends TestCase {
  protected $jorge;
  protected $tempDir;

  /**
   * Creates a Jorge-like object on which we can test output.
   */
  // protected function setUp(): void {
  //   $this->tempDir = (new TemporaryDirectory())->create();
  //   $root = $this->tempDir->path();
  //   mkdir($root . DIRECTORY_SEPARATOR . '.jorge');
  //   chdir($root);
  //   touch('.jorge' . DIRECTORY_SEPARATOR . 'config.yml');
  //   $this->jorge = new MockJorge($root);
  //   $this->jorge->configure();
  // }
  //
  // protected function tearDown(): void {
  //   $this->jorge = NULL;
  //   $this->tempDir->delete();
  // }

  public function test__Construct(): void {
    $this->expectException(LogicException::class);
    $tool = new Tool();
  }

  public function testGetStatus(): void {
    $name = bin2hex(random_bytes(4));
    $tool = new Tool($name);
    $this->assertFalse($tool->getStatus());
  }

  /**
   * This requires a MockTool because we need to capture output.
   */
  public function testRun(): void {
    $name = bin2hex(random_bytes(4));
    $tool = new MockTool($name);

    # Make sure run() fails if not enabled.
    $tool->run();
    $this->assertSame(1, count($tool->messages));
    $expected = ['error', 'Tool not enabled', []];
    $this->assertSame($expected, $tool->messages[0]);

    # Give it an executable and make sure it runs when enabled.
    $tool->setApplication(new Jorge(), 'echo');
    $tool->setStatus(TRUE);
    $this->assertTrue($tool->isEnabled());
    $result = $tool->run($name);
    $this->assertSame(0, $result);
    $this->assertSame(3, count($tool->messages));
    $executable = $tool->messages[1][2]['%executable'];
    $this->assertSame("$executable $name", $tool->messages[2][2]['%command']);
  }

  /**
  * This requires a MockTool because setExecutable is protected, and we
  * need to capture output.
   */
  public function testSetExecutable(): void {
    $name = bin2hex(random_bytes(4));
    $tool = new MockTool($name);
    $tool->setApplication(new Jorge(), $name);
    $this->assertSame(1, count($tool->messages));
    $expected = [
      'error',
      'Cannot set executable "{%executable}"',
      ['%executable' => $name],
    ];
    $this->assertSame($expected, $tool->messages[0]);
  }
}
