<?php

namespace App\Repository;

use App\Entity\Produit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Produit>
 */
class ProduitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Produit::class);
    }

    /**
     * @return Produit[] For shop: filter by category (optional), sort by field
     */
    public function findForShop(?string $categorie = null, ?string $tri = 'nom', ?string $ordre = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('p');
        if ($categorie !== null && $categorie !== '') {
            $qb->andWhere('p.categorie = :cat')
                ->setParameter('cat', $categorie);
        }
        $allowedSort = ['nom', 'prix', 'categorie'];
        if (!in_array($tri, $allowedSort, true)) {
            $tri = 'nom';
        }
        $qb->orderBy('p.' . $tri, $ordre === 'DESC' ? 'DESC' : 'ASC');
        return $qb->getQuery()->getResult();
    }

    public function findDistinctCategories(): array
    {
        $result = $this->createQueryBuilder('p')
            ->select('p.categorie')
            ->distinct()
            ->orderBy('p.categorie', 'ASC')
            ->getQuery()
            ->getSingleColumnResult();
        return array_values(array_filter($result));
    }

//    /**
//     * @return Produit[] Returns an array of Produit objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Produit
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
