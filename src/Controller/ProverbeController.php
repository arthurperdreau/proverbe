<?php

namespace App\Controller;

use App\Entity\Proverbe;
use App\Form\ProverbeForm;
use App\Repository\ProverbeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\BuilderInterface;
use Knp\Snappy\Pdf;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class ProverbeController extends AbstractController
{
    #[Route('/proverbe', name: 'app_proverbe')]
    public function index(ProverbeRepository $proverbeRepository): Response
    {
        return $this->render('proverbe/index.html.twig', [
            "proverbes" => $proverbeRepository->findAll(),
        ]);
    }

    #[Route('/proverbe/show/{id}', name: 'app_proverbe_show')]
    public function show(Proverbe $proverbe): Response
    {
        return $this->render('proverbe/show.html.twig', [
            "proverbe" => $proverbe,
        ]);
    }

    #[Route('/proverbe/new', name: 'app_proverbe_new')]
    public function create(Request $request, EntityManagerInterface $manager):Response
    {
        $user = $this->getUser();
        if (!$user || !in_array("ROLE_ADMIN", $user->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        if($this->getUser())
        $proverbe = new Proverbe();
        $form = $this->createForm(ProverbeForm::class, $proverbe);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $manager->persist($proverbe);
            $manager->flush();
            return $this->redirectToRoute('app_proverbe_show', ['id' => $proverbe->getId()]);
        }

        return $this->render('proverbe/create.html.twig', [
            'form' =>  $form->createView(),
        ]);
    }

    #[Route('/proverbe/qrcode/random', name: 'app_proverbe_qrcode_random')]
    public function randomQrCode(
        ProverbeRepository $proverbeRepository,
        BuilderInterface $defaultBuilder,
        UrlGeneratorInterface $urlGenerator
    ): Response {

        $proverbes = $proverbeRepository->findAll();
        if (count($proverbes) === 0) {
            throw $this->createNotFoundException('Aucun proverbe trouvé');
        }

        $proverbe = $proverbes[array_rand($proverbes)];

        $url = $urlGenerator->generate('app_proverbe_show', ['id' => $proverbe->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $result = $defaultBuilder->build(
            data: $url,
            size: 300,
            margin: 10
        );

        return $this->render('proverbe/qrcode.html.twig', [
            'proverbe' => $proverbe,
            'qrCode' => $result->getDataUri(),
        ]);
    }

    #[Route('/proverbe/pdf/{id}', name: 'app_proverbe_pdf')]
    public function proverbePdf(
        Proverbe $proverbe,
        Pdf $knpSnappyPdf,
        BuilderInterface $defaultBuilder,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $user = $this->getUser();
        if (!$user || !in_array("ROLE_ADMIN", $user->getRoles())) {
            return $this->redirectToRoute('app_login');
        }
        $url = $urlGenerator->generate('app_proverbe_show', [
            'id' => $proverbe->getId()
        ], UrlGeneratorInterface::ABSOLUTE_URL);

        $qrCode = $defaultBuilder->build(
            data: $url,
            size: 200,
            margin: 10
        )->getDataUri();

        $html = $this->renderView('proverbe/pdf.html.twig', [
            'proverbe' => $proverbe,
            'qrCode' => $qrCode
        ]);

        return new Response(
            $knpSnappyPdf->getOutputFromHtml($html),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="proverbe_' . $proverbe->getId() . '.pdf"',
            ]
        );
    }


}
