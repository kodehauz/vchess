<?php

namespace Drupal\Tests\vchess\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\user\Entity\Role;

class VChessUserTest extends KernelTestBase {

  public static $modules = ['user', 'gamer', 'pos'];

  /**
   * Tests that the user permissions for authenticated role is installed.
   */
  public function testUserPermissionsOnInstall() {
    Role::create(['id' => 'authenticated'])->save();
    $this->enableModules(['vchess']);
    include __DIR__ . '/../../../vchess.install';
    vchess_install();
    $authenticated_role = Role::load('authenticated');
    $this->assertTrue($authenticated_role->hasPermission('view player'));
    $this->assertTrue($authenticated_role->hasPermission('basic access'));
    $this->assertTrue($authenticated_role->hasPermission('view game'));
    $this->assertTrue($authenticated_role->hasPermission('view challenges'));
    $this->assertTrue($authenticated_role->hasPermission('accept challenge'));
    $this->assertTrue($authenticated_role->hasPermission('my current games'));
  }

}
