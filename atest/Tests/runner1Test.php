 
<?php
// test PHPUNIT
// Makegood evaluation


use PHPUnit\Framework\TestCase;

class StackTest1 extends TestCase
{
    public function testPushAndPop()
    {
        $stack = [1];
        $this->assertNotEmpty($stack);
        
    }  
    
    public function testExpectFooActualFoo()
    {
        $this->expectOutputString('foo');
        print 'foo';
    }
    
    public function testOne()
    {
        $this->assertTrue(true);
    }
    
    /**
     * @dataProvider additionProvider
     */
    public function testAdd($a, $b, $expected)
    {
        $this->assertEquals($expected, $a + $b);
    }
    
    public function additionProvider()
    {
        return [
            [0, 0, 0],
            [0, 1, 1],
            [1, 0, 1],
            [1, 1, 2]
        ];
    }
    
    /**
     * @dataProvider provider
     */
    public function testMethod($data)
    {
        $this->assertTrue($data);
    }
    
    public function provider()
    {
        return [
            'my named data' => [true],
            'my data'       => [true]
        ];
    }
    
    protected function setUp()
    {
        if (!extension_loaded('mysqli')) {
            $this->markTestSkipped(
                'The MySQLi extension is not available.'
                );
        }
    }
    
    public function testFailure()
    {
        $this->assertContains(3, [1, 2, 3]);
    }
    
}  
?>
