<?php

namespace App\Repository;

use App\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Article|null find($id, $lockMode = null, $lockVersion = null)
 * @method Article|null findOneBy(array $criteria, array $orderBy = null)
 * @method Article[]    findAll()
 * @method Article[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    /**
    * @return Article[] Returns an array of Article objects
    */
    
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('a.id', 'ASC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult()
        ;
    }
    

    
    public function findByTitle($value)
    {
        return $this->createQueryBuilder('a')
            ->Where('a.title LIKE :val') 
            ->andWhere('a.isPublished LIKE :val2')
            ->setParameters(array('val'=>'%'.$value.'%', 'val2'=> true))
            ->orderBy('a.createdAt', 'desc')
            ->getQuery()
            ->getResult()
        ;
    }
    
}
