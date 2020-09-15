<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Form\CommentType;
use App\Form\ReplyType;
use App\Form\SearchType;
use App\Repository\ArticleRepository;
use DateTime;
use Symfony\Component\HttpFoundation\Request; 
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

class BlogController extends AbstractController
{
    /**
     * @Route("/", name="homepage")
     */
    public function index(Request $request, PaginatorInterface $paginator, ArticleRepository $repo, SessionInterface $session)
    {
        $lastSearch = $session->get('lastSearch'); //tps depuis la derniere recherche
        if($lastSearch != null){
            $timeDiff = $lastSearch->diff(new DateTime());
            if($lastSearch == null || $timeDiff->i >= 4){ //si le temps de la last est > 4min => on clear la session
                $session->clear();
                return $this->redirect($this->generateUrl('homepage'));
            }        
            else { //sinon on regarde si on a des donnees lia a la search
                $donnees = $session->get('searchDonnees');
                if($donnees == null){
                    $donnees = $this->getDoctrine()->getRepository(Article::class)->findBy(['isPublished' => 1], ['createdAt' => 'desc']);
                }
            }
        }
        else { //si le tps est nul => pas de recherche, on affiche tout
            $donnees = $this->getDoctrine()->getRepository(Article::class)->findBy(['isPublished' => 1], ['createdAt' => 'desc']);
        }

        if($request->query->get('see_all') == 1){ //si on appuis sur "voir tous"
            $session->clear();
            return $this->redirect($this->generateUrl('homepage'));
        }
        
        $categories = $this->getDoctrine()->getRepository(Tag::class)->findAll();
        
        $seeAll = $session->get('see_all');

        $searchForm = $this->createForm(SearchType::class);
        $searchForm->handleRequest($request);

        if($searchForm->isSubmitted() && $searchForm->isValid()){
            $search = $searchForm->get('search')->getData();
            
            $searchDonnees = $repo->findByTitle($search);
            
            
            if($searchDonnees != null){
                $session->set('searchDonnees', $searchDonnees);
                $session->set('see_all', 1);
                $session->set('lastSearch', new DateTime());

            }
            else{
                $this->addFlash('error', 'Aucun article trouvé');
            }
            
            return $this->redirect($this->generateUrl('homepage'));
        }

        $articles = $paginator->paginate(
            $donnees, 
            $request->query->getInt('page', 1), 
            5
        );
        
        return $this->render('blog/index.html.twig', [
            'articles' => $articles,
            'searchForm' => $searchForm->createView(),
            'see_all' => $seeAll,
            'categories' => $categories
        ]);
    }

    /**
     *  @Route("/categorie/{category}", name="article_by_category")
     */
    public function articleByCategory(Request $request,PaginatorInterface $paginator, string $category, ArticleRepository $repo)
    {
        $categorie = $this->getDoctrine()->getRepository(Tag::class)->findOneBy(['name' => $category]);
        $categories = $this->getDoctrine()->getRepository(Tag::class)->findAll();
        
        
        if($categorie == null){
            throw $this->createNotFoundException('Cette catégorie n\'existe pas ou ne comporte pas d\'article.');
        }
        else {
            $donnees = $categorie->getArticles();
        }

        $searchForm = $this->createForm(SearchType::class);
        $searchForm->handleRequest($request);

        if($searchForm->isSubmitted() && $searchForm->isValid()){
            $search = $searchForm->get('search')->getData();
            
            $searchDonnees = $repo->findByTitle($search);
            
            
            if($searchDonnees != null){
                $this->get('session')->set('searchDonnees', $searchDonnees);
                $this->get('session')->set('see_all', 1);
                $this->get('session')->set('lastSearch', new DateTime());

            }
            else{
                $this->addFlash('error', 'Aucun article trouvé');
            }
            
            return $this->redirect($this->generateUrl('homepage'));
        }

        $articles = $paginator->paginate(
            $donnees, 
            $request->query->getInt('page', 1), 
            5
        );
        
        return $this->render('blog/index.html.twig', [
            'articles' => $articles,
            'searchForm' => $searchForm->createView(),
            'see_all' => 1,
            'categories' => $categories,
        ]);
    }

    /**
     * @Route("/article/{slug}", name="show_article")
     */
    public function showArticle(Request $request, string $slug, ArticleRepository $repo)
    {
        $article = $this->getDoctrine()->getRepository(Article::class)->findOneBy(['slug' => $slug, 'isPublished' => 1]);
        if($article == null){
            throw $this->createNotFoundException('Cet article n\'existe pas !');
        }
        $comments = $this->getDoctrine()->getRepository(Comment::class)->findBy(['post' => $article, 'isVerified' => true], ['commentDate' => 'desc']);
        $categories = $this->getDoctrine()->getRepository(Tag::class)->findAll();
        
        $comment = new Comment();
        $commentForm = $this->createForm(CommentType::class, $comment);

        $reply = new Reply();
        $replyForm = $this->createForm(ReplyType::class);

        $searchForm = $this->createForm(SearchType::class);
        $searchForm->handleRequest($request);

        $commentForm->handleRequest($request);
        $replyForm->handleRequest($request);

        if($commentForm->isSubmitted() && $commentForm->isValid()){

            $comment->setAuthor($this->getUser());
            $comment->setCommentDate(new DateTime());
            $comment->setPost($article);
            $comment->setIsVerified(false);

            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();
            $this->addFlash('success', 'Votre commentaire à bien été créé. Il sera bientôt validé par le staff');
            
            return $this->redirect($this->generateUrl('show_article', ['slug' => $slug]));
        }

        if($replyForm->isSubmitted() && $replyForm->isValid()){
            $id = $replyForm->get('parent')->getData();
            $reply->setContent($replyForm->get('content')->getData());
            $parent = $this->getDoctrine()->getRepository(Comment::class)->findOneBy(['id' => $id]);
            $reply->setAuthor($this->getUser());
            $reply->setCommentDate(new DateTime());
            $reply->setParent($parent);
            $reply->setIsVerified(false);

            $em = $this->getDoctrine()->getManager();
            $em->persist($reply);
            $em->flush();
            $this->addFlash('success', 'Votre réponse à bien été créé. Elle sera bientôt validée par le staff');

            return $this->redirect($this->generateUrl('show_article', ['slug' => $slug]));
        }

        if($searchForm->isSubmitted() && $searchForm->isValid()){
            $search = $searchForm->get('search')->getData();
            
            $searchDonnees = $repo->findByTitle($search);
            
            
            if($searchDonnees != null){
                $this->get('session')->set('searchDonnees', $searchDonnees);
                $this->get('session')->set('see_all', 1);
                $this->get('session')->set('lastSearch', new DateTime());

            }
            else{
                $this->addFlash('error', 'Aucun article trouvé');
            }
            
            return $this->redirect($this->generateUrl('homepage'));
        }

        $this->get('session')->clear();
        
        return $this->render('blog/show.html.twig', [
            'article' => $article,
            'comments' => $comments,
            'commentForm' =>$commentForm->createView(),
            'replyForm' =>$replyForm->createView(),
            'searchForm' => $searchForm->createView(),
            'categories' => $categories
        ]);
    }


}
