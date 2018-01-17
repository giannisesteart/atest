 
<?php
use PHPUnit\Framework\TestCase;

/**
 * @author gIANNIS
 * Test phpUnit
 * Makegood evaluation
 *
 */
class StackTest extends TestCase
{

     /**
      * 
      */
     public function testPushAndPop1()  
    {
        $stack = [];
        $this->assertEquals(0, count($stack));
        array_push($stack, 'foo');
        $this->assertEquals('foo', $stack[count($stack) - 1]);
        $this->assertEquals(1, count($stack));
        $this->assertEquals('foo', array_pop($stack));
        $this->assertEquals(0, count($stack));
        echo "ok 3";
    }

    /**
     *
     * @return number
     */
    public function get()
    {
        return 0;
    }

    /**
     * @covers get
     */
    public function testBalanceIsInitiallyZero()
    {
        $this->assertEquals(0, $this->get());
    }
}
?>
