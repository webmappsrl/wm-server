<?php
use PHPUnit\Framework\TestCase;

class ReadConfTest extends TestCase
{
    // ...

    public function testCanBeNegated()
    {
        // Arrange
        $a = new ReadConf(1);

        // Act
        $b = $a->negate();

        // Assert
        $this->assertEquals(-1, $b->getAmount());
    }

    // ...
}