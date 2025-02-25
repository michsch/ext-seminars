<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Tests\LegacyUnit\Model;

use OliverKlee\Seminars\Model\Checkbox;
use PHPUnit\Framework\TestCase;

final class CheckboxTest extends TestCase
{
    /**
     * @var Checkbox
     */
    private $subject;

    protected function setUp(): void
    {
        $this->subject = new Checkbox();
    }

    /**
     * @test
     */
    public function setTitleWithEmptyTitleThrowsException(): void
    {
        $this->expectException(
            \InvalidArgumentException::class
        );
        $this->expectExceptionMessage(
            'The parameter $title must not be empty.'
        );

        $this->subject->setTitle('');
    }

    /**
     * @test
     */
    public function setTitleSetsTitle(): void
    {
        $this->subject->setTitle('I agree with the T&C.');

        self::assertEquals(
            'I agree with the T&C.',
            $this->subject->getTitle()
        );
    }

    /**
     * @test
     */
    public function getTitleWithNonEmptyTitleReturnsTitle(): void
    {
        $this->subject->setData(['title' => 'I agree with the T&C.']);

        self::assertEquals(
            'I agree with the T&C.',
            $this->subject->getTitle()
        );
    }

    /////////////////////////////////////
    // Tests regarding the description.
    /////////////////////////////////////

    /**
     * @test
     */
    public function getDescriptionWithoutDescriptionReturnsAnEmptyString(): void
    {
        $this->subject->setData([]);

        self::assertEquals(
            '',
            $this->subject->getDescription()
        );
    }

    /**
     * @test
     */
    public function getDescriptionWithDescriptionReturnsDescription(): void
    {
        $this->subject->setData(['description' => 'I agree with the T&C.']);

        self::assertEquals(
            'I agree with the T&C.',
            $this->subject->getDescription()
        );
    }

    /**
     * @test
     */
    public function setDescriptionSetsDescription(): void
    {
        $this->subject->setDescription('I agree with the T&C.');

        self::assertEquals(
            'I agree with the T&C.',
            $this->subject->getDescription()
        );
    }

    /**
     * @test
     */
    public function hasDescriptionWithoutDescriptionReturnsFalse(): void
    {
        $this->subject->setData([]);

        self::assertFalse(
            $this->subject->hasDescription()
        );
    }

    /**
     * @test
     */
    public function hasDescriptionWithDescriptionReturnsTrue(): void
    {
        $this->subject->setDescription('I agree with the T&C.');

        self::assertTrue(
            $this->subject->hasDescription()
        );
    }
}
