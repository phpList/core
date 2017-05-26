<?php
namespace phpList\test;

use phpList\EmailUtil;
use PHPUnit\Framework\TestCase;

class EmailUtilTest extends TestCase
{
    public function setUp()
    {
        $this->emailUtil = new EmailUtil();
    }

    public function testInvalidEmail1()
    {
        $address = 'invalid';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testInvalidEmail2()
    {
        $address = 'invalid@email';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testInvalidEmail3()
    {
        $address = 'invalid@email.addre,ss';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testInvalidEmail4()
    {
        $address = 'me.@example.com';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testInvalidEmail5()
    {
        $address = '.me@example.com';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testInvalidEmail6()
    {
        $address = 'me@example..com';

        $this->assertFalse($this->emailUtil->isValid($address));
    }

    public function testValidEmail1()
    {
        $address = 'valid@email.com';

        $this->assertTrue($this->emailUtil->isValid($address));
    }

    public function testValidEmail2()
    {
        $address = 'val-id@email.com';

        $this->assertTrue($this->emailUtil->isValid($address));
    }

    public function testValidEmail3()
    {
        $address = 'val&id@email.com';

        $this->assertTrue($this->emailUtil->isValid($address));
    }

    public function testValidEmail4()
    {
        $address = 'val.id@email.com';

        $this->assertTrue($this->emailUtil->isValid($address));
    }

    public function testValidEmail5()
    {
        $address = 'val+id@email.com';

        $this->assertTrue($this->emailUtil->isValid($address));
    }

    public function testTlds()
    {
        // Set restrictive tld list
        $tlds = 'com|net';

        // Test allowed tld passes
        $this->assertTrue($this->emailUtil->isValid('valid@email.com', $tlds));
        // Test disallowed tld does not
        $this->assertFalse($this->emailUtil->isValid('valid@email.xxx', $tlds));
    }
}
