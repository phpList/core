<?php
namespace phpList\test;

use phpList\Pass;

class PassTest extends \PHPUnit_Framework_TestCase {

    public function setUp()
    {
        $this->pass = new Pass();
        $this->plainPass = 'easypassword';
    }

    public function testEncrypt()
    {
        $encPass = $this->pass->encrypt( $this->plainPass );
        // Check the result is of hash length
        $this->assertGreaterThan( 30, strlen( $encPass ) );
        // Check result is changed from original
        $this->assertNotEquals( $this->plainPass, $encPass );
    }
}
