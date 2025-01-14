<?php

declare(strict_types=1);

namespace OliverKlee\Seminars\Domain\Repository\Registration;

use OliverKlee\Oelib\Domain\Repository\Interfaces\DirectPersist;
use OliverKlee\Oelib\Domain\Repository\Traits\StoragePageAgnostic;
use OliverKlee\Seminars\Domain\Model\Event\EventInterface;
use OliverKlee\Seminars\Domain\Model\Registration\Registration;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Registration>
 */
class RegistrationRepository extends Repository implements DirectPersist
{
    use \OliverKlee\Oelib\Domain\Repository\Traits\DirectPersist;
    use StoragePageAgnostic;

    public function existsRegistrationForEventAndUser(EventInterface $event, int $userUid): bool
    {
        $query = $this->createQuery();
        $query->matching(
            $query->logicalAnd(
                $query->equals('seminar', $event),
                $query->equals('user', $userUid)
            )
        );

        return $query->count() > 0;
    }
}
