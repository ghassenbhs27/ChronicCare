<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;

class ActivityBadgeService
{
    private const BADGE_TITLE = 'Utilisateur le plus actif';
    private const BADGE_REWARD = 'Bon d achat de 50 DT sur la boutique Chronic Care';

    public function __construct(
        private readonly Connection $connection,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    /**
     * @return array{winner: ?User, score: int}
     */
    public function assignMostActiveBadge(int $days = 30): array
    {
        $since = (new \DateTimeImmutable())->modify(sprintf('-%d days', max(1, $days)));
        $scores = $this->computeScores($since);
        $users = $this->userRepository->findAll();

        $winner = null;
        $winnerScore = 0;

        foreach ($users as $user) {
            $score = (int) ($scores[$user->getId() ?? 0] ?? 0);
            $user->setActivityScore($score);
            $user->setIsMostActive(false);
            $user->setActivityBadge(null);
            $user->setActivityReward(null);
            $user->setActivityBadgeAwardedAt(null);

            if ($score > $winnerScore) {
                $winner = $user;
                $winnerScore = $score;
            }
        }

        if ($winner instanceof User && $winnerScore > 0) {
            $winner->setIsMostActive(true);
            $winner->setActivityBadge(self::BADGE_TITLE);
            $winner->setActivityReward(self::BADGE_REWARD);
            $winner->setActivityBadgeAwardedAt(new \DateTimeImmutable());
        }

        $this->entityManager->flush();

        return [
            'winner' => $winner,
            'score' => $winnerScore,
        ];
    }

    public function assignBadgeToUser(User $winner, ?string $reward = null): void
    {
        $users = $this->userRepository->findAll();
        foreach ($users as $user) {
            $user->setIsMostActive(false);
            $user->setActivityBadge(null);
            $user->setActivityReward(null);
            $user->setActivityBadgeAwardedAt(null);
        }

        $winner->setIsMostActive(true);
        $winner->setActivityBadge(self::BADGE_TITLE);
        $winner->setActivityReward($reward ?: self::BADGE_REWARD);
        $winner->setActivityBadgeAwardedAt(new \DateTimeImmutable());
        $winner->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();
    }

    /**
     * @return array<int,int>
     */
    private function computeScores(\DateTimeImmutable $since): array
    {
        $scores = [];
        $weights = [
            'activite' => 3,
            'etat' => 3,
            'publication' => 5,
            'commentaire' => 2,
            'commande' => 4,
        ];

        $this->accumulate(
            $scores,
            $weights['activite'],
            'SELECT utilisateur_id AS user_id, COUNT(*) AS total FROM activite WHERE utilisateur_id IS NOT NULL AND created_at >= :since GROUP BY utilisateur_id',
            $since
        );
        $this->accumulate(
            $scores,
            $weights['etat'],
            'SELECT utilisateur_id AS user_id, COUNT(*) AS total FROM etat WHERE utilisateur_id IS NOT NULL AND date_releve >= :since GROUP BY utilisateur_id',
            $since
        );
        $this->accumulate(
            $scores,
            $weights['publication'],
            'SELECT auteur_id AS user_id, COUNT(*) AS total FROM publication WHERE auteur_id IS NOT NULL AND created_at >= :since GROUP BY auteur_id',
            $since
        );
        $this->accumulate(
            $scores,
            $weights['commentaire'],
            'SELECT auteur_id AS user_id, COUNT(*) AS total FROM commentaire WHERE auteur_id IS NOT NULL AND created_at >= :since GROUP BY auteur_id',
            $since
        );
        $this->accumulate(
            $scores,
            $weights['commande'],
            'SELECT utilisateur_id AS user_id, COUNT(*) AS total FROM commande WHERE utilisateur_id IS NOT NULL AND created_at >= :since GROUP BY utilisateur_id',
            $since
        );
        return $scores;
    }

    /**
     * @param array<int,int> $scores
     */
    private function accumulate(array &$scores, int $weight, string $sql, \DateTimeImmutable $since): void
    {
        $rows = $this->connection->fetchAllAssociative($sql, ['since' => $since], ['since' => Types::DATETIME_IMMUTABLE]);
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $total = (int) ($row['total'] ?? 0);
            if ($userId <= 0 || $total <= 0) {
                continue;
            }
            $scores[$userId] = ($scores[$userId] ?? 0) + ($total * $weight);
        }
    }
}
