<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\OldModel;

use OliverKlee\Seminars\Hooks\HookProvider;
use OliverKlee\Seminars\Hooks\Interfaces\DateTimeSpan;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * This class offers timespan-related methods for the time slot and seminar classes.
 */
abstract class AbstractTimeSpan extends AbstractModel
{
    /**
     * @var HookProvider|null
     */
    protected $dateTimeSpanHookProvider;

    /**
     * Gets the begin date.
     *
     * @return string the begin date (or the localized string "will be announced" if no begin date is set)
     */
    public function getBeginDate(): string
    {
        return $this->hasBeginDate()
            ? \strftime($this->getDateFormat(), $this->getBeginDateAsTimestamp())
            : $this->translate('message_willBeAnnounced');
    }

    public function hasBeginDate(): bool
    {
        return $this->getBeginDateAsTimestamp() > 0;
    }

    /**
     * @return string the end date (or the localized string "will be announced" if no end date is set)
     */
    public function getEndDate(): string
    {
        return $this->hasEndDate()
            ? \strftime($this->getDateFormat(), $this->getEndDateAsTimestamp())
            : $this->translate('message_willBeAnnounced');
    }

    public function hasEndDate(): bool
    {
        return $this->getEndDateAsTimestamp() > 0;
    }

    /**
     * Checks whether there's a begin date set, and whether this has already passed.
     *
     * @return bool true if the time-span has a begin date set that lies in
     *                 the future (time-span has not started yet), false otherwise
     */
    public function hasStarted(): bool
    {
        return $this->hasBeginDate() && (int)$GLOBALS['SIM_EXEC_TIME'] >= $this->getBeginDateAsTimestamp();
    }

    /**
     * Gets the date.
     *
     * Returns a localized string "will be announced" if there's no date set.
     *
     * Returns just one day if the timespan takes place on only one day.
     * Returns a date range if the timespan takes several days.
     *
     * @param string $dash the character or HTML entity used to separate start date and end date
     */
    public function getDate(string $dash = '&#8211;'): string
    {
        if ($this->hasDate()) {
            $beginDate = $this->getBeginDateAsTimestamp();
            $endDate = $this->getEndDateAsTimestamp();

            $dateFormat = $this->getDateFormat();
            $beginDateDay = \strftime($dateFormat, $beginDate);
            $endDateDay = \strftime($dateFormat, $endDate);

            // Does the workshop span only one day (or is open-ended)?
            if ($beginDateDay === $endDateDay || !$this->hasEndDate()) {
                $result = $beginDateDay;
            } else {
                $resultBeforeHook = $beginDateDay . $dash . $endDateDay;
                $result = $this->getDateTimeSpanHookProvider()->executeHookReturningModifiedValue(
                    'modifyDateSpan',
                    $resultBeforeHook,
                    $this,
                    $dash
                );
            }
        } else {
            $result = $this->translate('message_willBeAnnounced');
        }

        return (string)$result;
    }

    /**
     * Checks whether there's a (begin) date set.
     *
     * If there's an end date but no begin date, this function still will return false.
     */
    public function hasDate(): bool
    {
        return $this->hasRecordPropertyInteger('begin_date');
    }

    /**
     * Gets the time.
     *
     * Returns a localized string "will be announced" if there's no time set
     * (i.e. both begin time and end time are 00:00).
     * Returns only the begin time if begin time and end time are the same.
     *
     * @param string $dash the character or HTML entity used to separate begin time and end time
     */
    public function getTime(string $dash = '&#8211;'): string
    {
        if (!$this->hasTime()) {
            return $this->translate('message_willBeAnnounced');
        }

        $timeFormat = $this->getTimeFormat();
        $beginTime = \strftime($timeFormat, $this->getBeginDateAsTimestamp());
        $endTime = \strftime($timeFormat, $this->getEndDateAsTimestamp());

        $result = $beginTime;

        // Only display the end time if the event has an end date/time set
        // and the end time is not the same as the begin time.
        if (($beginTime !== $endTime) && $this->hasEndTime()) {
            $result .= $dash . $endTime;
            $result = $this->getDateTimeSpanHookProvider()->executeHookReturningModifiedValue(
                'modifyTimeSpan',
                $result,
                $this,
                $dash
            );
        }
        $hours = $this->translate('label_hours');
        $result .= ' ' . $hours;

        return $result;
    }

    /**
     * Checks whether there's a time set (begin time != 00:00)
     *
     * If there's no date/time set, the result will be false.
     */
    public function hasTime(): bool
    {
        $beginTime = \strftime('%H:%M', $this->getBeginDateAsTimestamp());

        return $this->hasDate() && $beginTime !== '00:00';
    }

    /**
     * Checks whether there's an end time set (end time != 00:00).
     *
     * If there's no end date/time set, the result will be false.
     */
    public function hasEndTime(): bool
    {
        $endTime = strftime('%H:%M', $this->getEndDateAsTimestamp());

        return $this->hasEndDate() && $endTime !== '00:00';
    }

    /**
     * @return int our begin date and time as a UNIX timestamp or 0 if we don't have a begin date
     */
    public function getBeginDateAsTimestamp(): int
    {
        return $this->getRecordPropertyInteger('begin_date');
    }

    /**
     * @return int our end date and time as a UNIX timestamp or 0 if we don't have an end date
     */
    public function getEndDateAsTimestamp(): int
    {
        return $this->getRecordPropertyInteger('end_date');
    }

    /**
     * Gets our end date and time as a UNIX timestamp. If this event is
     * open-ended, midnight after the begin date and time is returned.
     * If we don't even have a begin date, 0 is returned.
     *
     * @return int our end date and time as a UNIX timestamp, 0 if we don't have a begin date
     */
    public function getEndDateAsTimestampEvenIfOpenEnded(): int
    {
        $result = 0;

        if ($this->hasBeginDate()) {
            if ($this->isOpenEnded()) {
                $splitBeginDate = getdate($this->getBeginDateAsTimestamp());
                $result = mktime(
                    0,
                    0,
                    0,
                    (int)$splitBeginDate['mon'],
                    (int)$splitBeginDate['mday'] + 1,
                    (int)$splitBeginDate['year']
                );
            } else {
                $result = $this->getEndDateAsTimestamp();
            }
        }

        return $result;
    }

    /**
     * Gets the seminar room (not the site).
     *
     * @return string the seminar room (may be empty)
     */
    public function getRoom(): string
    {
        return $this->getRecordPropertyString('room');
    }

    public function hasRoom(): bool
    {
        return $this->hasRecordPropertyString('room');
    }

    /**
     * Checks whether this time span is open-ended.
     *
     * A time span is considered to be open-ended if it does not have an end date.
     */
    public function isOpenEnded(): bool
    {
        return !$this->hasEndDate();
    }

    public function hasPlace(): bool
    {
        return $this->hasRecordPropertyInteger('place');
    }

    /**
     * Gets our place(s) as plain text (just the places name).
     *
     * Returns a localized string "will be announced" if the time slot has no place set.
     *
     * @return string our places or an empty string if the timespan has no places
     */
    abstract public function getPlaceShort(): string;

    protected function getDateTimeSpanHookProvider(): HookProvider
    {
        if (!$this->dateTimeSpanHookProvider instanceof HookProvider) {
            $this->dateTimeSpanHookProvider = GeneralUtility::makeInstance(HookProvider::class, DateTimeSpan::class);
        }

        return $this->dateTimeSpanHookProvider;
    }
}
