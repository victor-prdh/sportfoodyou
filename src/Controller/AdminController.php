<?php

namespace App\Controller;

use App\Entity\Article;
use App\Entity\Comment;
use App\Entity\Reply;
use App\Entity\Tag;
use App\Entity\User;
use App\Form\ArticleType;
use App\Form\CommentType;
use App\Form\EditReplyType;
use App\Form\ReplyType;
use App\Form\TagType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Response;

/**
* @Route("/admin")
* @IsGranted("ROLE_ADMIN", message="Accès refusé")
*/
class AdminController extends AbstractController
{
    /**
     * @Route("", name="admin")
     */
    public function index()
    {
        $articles = $this->getDoctrine()->getRepository(Article::class)->findBy(['isPublished' => "1"]);
        $users = $this->getDoctrine()->getRepository(User::class)->findAll();
        $tags = $this->getDoctrine()->getRepository(Tag::class)->findAll();
        $comments = $this->getDoctrine()->getRepository(Comment::class)->findAll();
        $replys = $this->getDoctrine()->getRepository(Reply::class)->findAll();

        return $this->render('admin/index.html.twig', [
            'articles' => $articles,
            'users' => $users,
            'tags' => $tags,
            'comments' => $comments,   
            'replys' => $replys,  
        ]);
    }

    //              //
    // PARTIE  BLOG //
    //              //

    //--- ARTICLES ---//

    /**
     * @Route("/blog/article", name="article_admin")
     */
    public function article()
    {
        $articles = $this->getDoctrine()->getRepository(Article::class)->findBy([],['createdAt' => 'desc']);
        return $this->render('admin/articles.html.twig', [
            'articles' => $articles,
        ]);
    }

    /**
     * @Route("/blog/article/add", name="add_article_admin")
     */
    public function addArticle(Request $request)
    {
        $article = new Article();

        $form = $this->createForm(ArticleType::class, $article);

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $article->setCreatedAt(new \DateTime());

            if ($article->getImage() !== null) {
                $file = $form->get('image')->getData();
                $fileName =  uniqid(). '.' .$file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory'), // Le dossier dans le quel le fichier va etre charger
                        $fileName
                    );
                } catch (FileException $e) {
                    return new Response($e->getMessage());
                }

                $article->setImage($fileName);
            } else{
                $article->setImage('1920x1080.png');
            }
            $article->setAuthor($this->getUser());


            $em = $this->getDoctrine()->getManager(); 
            $em->persist($article); 
            $em->flush(); 
            
            $this->addFlash('success', 'Votre article a bien été créé');
            return $this->redirect($this->generateUrl('add_article_admin'));
        }

        return $this->render('admin/add.html.twig', [
            'form' => $form->createView()
        ]);
    }

     /**
     * @Route("/blog/article/edit/{slug}", name="edit_article_admin")
     */
    public function editArticle(Request $request, Article $article, string $slug)
    {
        $oldImage = $article->getImage();

        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {


            if ($article->getImage() !== null && $article->getImage() !== $oldImage) {
                $file = $form->get('image')->getData();
                $fileName = uniqid(). '.' .$file->guessExtension();

                try {
                    $file->move(
                        $this->getParameter('images_directory'),
                        $fileName
                    );
                } catch (FileException $e) {
                    return new Response($e->getMessage());
                }

                $article->setImage($fileName);
            } else {
                $article->setImage($oldImage);
            }

            $em = $this->getDoctrine()->getManager();
            $em->persist($article);
            $em->flush();

            $this->addFlash('success', 'Votre article a bien été modifié');
            return $this->redirect($this->generateUrl('edit_article_admin', ['slug' => $slug]));
        }

        return $this->render('admin/edit.html.twig', [
            'article' => $article,
            'form' => $form->createView(),
            'picture' => $oldImage
        ]);
    
    }

    /**
     * @Route("/blog/article/delete/{slug}", name="delete_article_admin")
     */
    public function deleteArticle(string $slug)
    {
        $article = $this->getDoctrine()->getRepository(Article::class)->findOneBy(['slug' => $slug]);
        if($article == null){
            $this->addFlash('error', 'L\'article que vous tentez de supprimer n\'existe pas');
            return $this->redirectToRoute('admin');
        }else{
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Votre article a bien été supprimé');
            return $this->redirectToRoute('admin');
        }
    }


    //--- CATEGORIES ---//

    /**
     * @Route("/blog/categorie", name="categorie_admin")
     */
    public function Categorie(Request $request)
    {

        $newTag = new Tag();
        $tags = $this->getDoctrine()->getRepository(Tag::class)->findBy([], ['name' => 'asc']);
        $form = $this->createForm(TagType::class, $newTag);

        $form->handleRequest($request);

        if($form->isSubmitted() && $form->isValid()){

            $em = $this->getDoctrine()->getManager();
            $em->persist($newTag);
            $em->flush();
            $this->addFlash('success', 'Votre catégorie à bien été créée');
            
            return $this->redirect($this->generateUrl('categorie_admin'));
        }
        return $this->render('admin/add_categorie.html.twig', [
            'form' => $form->createView(),
            'tags' => $tags
        ]);
    }

    /**
     * @Route("/blog/categorie/delete/{name}", name="delete_categorie_admin")
     */
    public function deleteCategorie(string $name)
    {     
        $article = $this->getDoctrine()->getRepository(Tag::class)->findOneBy(['name' => $name]);
        if($article == null){
            $this->addFlash('error', 'La catégorie que vous tentez de supprimer n\'existe pas');
            return $this->redirectToRoute('categorie_admin');
        }else{
            $em = $this->getDoctrine()->getManager();
            $em->remove($article);
            $em->flush();
            $this->addFlash('success', 'Votre catégorie a bien été supprimé');
            return $this->redirectToRoute('categorie_admin');
        }
    }
    
    //--- UTILISATEURS ---//

    /**
     * @Route("/blog/user", name="user_admin")
     */
    public function user(Request $request)
    {

        $users = $this->getDoctrine()->getRepository(User::class)->findAll();

        return $this->render('admin/user.html.twig', [
            'users' => $users
        ]);
    }

    /**
     * @Route("/blog/user/delete/{id}", name="delete_user_admin")
     */
    public function deleteUser(int $id)
    {     
        $user = $this->getDoctrine()->getRepository(User::class)->findOneBy(['id' => $id]);
        
        if($user == null){
            $this->addFlash('error', 'L\'utilisateur que vous tentez de supprimer n\'existe pas');
            return $this->redirectToRoute('user_admin');
        }else{
            $em = $this->getDoctrine()->getManager();
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'L\'utilisateur a bien été supprimé');
            return $this->redirectToRoute('user_admin');
        }
    }


    //--- COMMENTAIRES ---//

    /**
     * @Route("/blog/comment", name="comment_admin")
     */
    public function comment(Request $request)
    {

        $comments = $this->getDoctrine()->getRepository(Comment::class)->findBy([], ['commentDate' => 'desc'], 20);
        $replys = $this->getDoctrine()->getRepository(Reply::class)->findBy([], ['commentDate' => 'desc'], 20);

        return $this->render('admin/comment.html.twig', [
            'comments' => $comments,
            'replys' => $replys

        ]);
    }

    /**
     * @Route("/blog/comment/delete/{id}", name="delete_comment_admin")
     */
    public function deleteComment(int $id)
    {     
        $comment = $this->getDoctrine()->getRepository(Comment::class)->findOneBy(['id' => $id]);
        
        if($comment == null){
            $this->addFlash('error', 'Le commentaire que vous tentez de supprimer n\'existe pas');
            return $this->redirectToRoute('comment_admin');
        }else{
            $em = $this->getDoctrine()->getManager();
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Le commentaire a bien été supprimé');
            return $this->redirectToRoute('comment_admin');
        }
    }

    /**
     * @Route("/blog/comment/edit/{id}", name="edit_comment_admin")
     */
    public function editComment(Request $request, Comment $comment, int $id)
    {
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isVerified = $request->get('isVerified');
            if(!$isVerified){
                $comment->setIsVerified(false);
            }
            else{
                $comment->setIsVerified(true);
            }
            
            $em = $this->getDoctrine()->getManager();
            $em->persist($comment);
            $em->flush();

            $this->addFlash('success', 'Le commentaire a bien été modifié');
            return $this->redirect($this->generateUrl('edit_comment_admin', ['id' => $id]));
        }

        return $this->render('admin/edit_comment.html.twig', [
            'form' => $form->createView(),
            'comment' => $comment,
        ]);
    }

    /**
     * @Route("/blog/reply/delete/{id}", name="delete_reply_admin")
     */
    public function deletereply(int $id)
    {     
        $reply = $this->getDoctrine()->getRepository(Reply::class)->findOneBy(['id' => $id]);
        
        if($reply == null){
            $this->addFlash('error', 'La réponse que vous tentez de supprimer n\'existe pas');
            return $this->redirectToRoute('comment_admin');
        }else{
            $em = $this->getDoctrine()->getManager();
            $em->remove($reply);
            $em->flush();
            $this->addFlash('success', 'La réponse a bien été supprimé');
            return $this->redirectToRoute('comment_admin');
        }
    }

    /**
     * @Route("/blog/reply/edit/{id}", name="edit_reply_admin")
     */
    public function editreply(Request $request, Reply $reply, int $id)
    {
        $comment = $reply->getParent();

        $form = $this->createForm(EditReplyType::class, $reply);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $isVerified = $request->get('isVerified');
            if(!$isVerified){
                $reply->setIsVerified(false);
            }
            else{
                $reply->setIsVerified(true);
            }
            
            $em = $this->getDoctrine()->getManager();
            $em->persist($reply);
            $em->flush();

            $this->addFlash('success', 'La réponse a bien été modifié');
            return $this->redirect($this->generateUrl('edit_reply_admin', ['id' => $id]));
        }

        return $this->render('admin/edit_reply.html.twig', [
            'form' => $form->createView(),
            'reply' => $reply,
            'comment' => $comment,
        ]);
    }

    

}
