<?php

namespace Imanaging\CheckFormatBundle\Controller;

use App\Entity\MappingConfiguration;
use DateTime;
use Imanaging\CheckFormatBundle\Service\DecimalToInteger;
use Imanaging\CheckFormatBundle\Service\DistinctMappingValues;
use Imanaging\CheckFormatBundle\Service\AdvancedPartTransformation;
use Imanaging\CheckFormatBundle\Service\CsvStructureComparison;
use Imanaging\CheckFormatBundle\Service\MappingStructurePreview;
use Imanaging\CheckFormatBundle\Exception\TransformationException;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceAutoIncrementInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceSaisieManuelleInterface;
use Symfony\Component\HttpFoundation\Response;
use Exception;
use Imanaging\CheckFormatBundle\Enum\TransformationEnum;
use Imanaging\CheckFormatBundle\Interfaces\MappingChampPossibleInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceDateCustomInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceFileInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceMultiColumnArrayInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTextInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueAvanceTypeInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTransformationInterface;
use Imanaging\CheckFormatBundle\Interfaces\MappingConfigurationValueTranslationInterface;
use Imanaging\CheckFormatBundle\Mapping;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

class MappingController extends AbstractController
{
  private $em;
  private $mapping;
  private $projectDir;
  private $twig;
  private CsrfTokenManagerInterface $csrfTokens;

  /**
   * MappingController constructor.
   * @param EntityManagerInterface $em
   * @param Mapping $mapping
   * @param $projectDir
   */
  public function __construct(EntityManagerInterface $em, Mapping $mapping, Environment $twig, CsrfTokenManagerInterface $csrfTokens)
  {
    $this->em = $em;
    $this->mapping = $mapping;
    $this->projectDir = $mapping->getProjectDir();
    $this->twig = $twig;
    $this->csrfTokens = $csrfTokens;
  }

  public function indexAction(Request $request)
  {
    $params = $request->request->all();
    $returnToTypes = $request->isMethod('GET');
    if ($returnToTypes) {
      $context = $request->getSession()->get('_check_format_type_selection', []);
      $code = $request->query->get('type');
      $codes = $context['codes'] ?? [];
      if ($codes === []) {
        $types = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findAll();
        $codes = array_map(static fn($type) => $type->getCode(), $types);
      } elseif (!is_string($code) || !in_array($code, $codes, true)) {
        $codes = is_string($code) ? [$code] : [];
      }
      $params = array_combine($codes, $codes) ?: [];
      $params['basePath'] = $context['basePath'] ?? 'base.html.twig';
    }
    $mappingsConfigurationsTypes = [];
    $othersParam = '';
    foreach ($params as $param => $value){
      if ($param == $value){
        $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $value]);
        if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
          $mappingsConfigurationsTypes[] = $mappingConfigurationType;
        }
      } elseif ($param != 'basePath') {
        if ($othersParam == '') {
          $othersParam = '?' . $param . '=' . $value;
        } else {
          $othersParam = '&' . $param . '=' . $value;
        }
      }
    }
    if (!$returnToTypes) {
      $request->getSession()->set('_check_format_type_selection', [
        'codes' => array_map(static fn($type) => $type->getCode(), $mappingsConfigurationsTypes),
        'basePath' => $params['basePath'] ?? 'base.html.twig',
      ]);
    }
    if (!$returnToTypes && count($mappingsConfigurationsTypes) == 1){
      return $this->redirect($this->generateUrl('check_format_mapping_page', ['code' => $mappingsConfigurationsTypes[0]->getCode()]).$othersParam);
    }

    $pendingFilesByType = [];
    foreach ($mappingsConfigurationsTypes as $type) {
      $pattern = $this->projectDir . $type->getFilesDirectory() . $type->getFilename() . '*';
      $pendingFilesByType[$type->getCode()] = count(glob($pattern) ?: []);
    }

    return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/index.html.twig', [
      'mappings_configurations_types' => $mappingsConfigurationsTypes,
      'pending_files_by_type' => $pendingFilesByType,
      'basePath' => $params['basePath'] ?? 'base.html.twig'
    ]));
  }

  public function mappingPageAction($code, Request $request)
  {
    ini_set('memory_limit', '512M');
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $directory = $this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*';
      $fichiersClient = glob($directory) ?: [];
      $repository = $this->em->getRepository(MappingConfigurationInterface::class);
      $configurations = $repository->findBy(['type' => $mappingConfigurationType]);
      $active = $this->getMappingConfigurationActive($mappingConfigurationType);
      $pendingOwnerId = null;
      $pendingConflict = false;
      foreach ($fichiersClient as $file) {
        $record = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy(['filename' => basename($file)]);
        $ownerId = $record instanceof MappingConfigurationFileInterface ? (string) $record->getMappingConfiguration()->getId()
        : ($active instanceof MappingConfigurationInterface ? (string) $active->getId() : null);
        if ($ownerId === null || ($pendingOwnerId !== null && $pendingOwnerId !== $ownerId)) {
          $pendingConflict = true;
        }
        $pendingOwnerId = $ownerId;
      }
      $error = $pendingConflict ? 'Les fichiers en attente ne peuvent pas être associés à une seule configuration. Vérifiez-les avant de poursuivre.' : null;

      if ($request->isMethod('POST') && $request->request->get('action') !== 'confirm_structure' && !$pendingConflict) {
        if (!$this->csrfTokens->isTokenValid(new CsrfToken('check_format_select_' . $code, $request->request->get('_token')))) {
          $error = 'La session a expiré. Veuillez réessayer.';
        } elseif (count($fichiersClient) > 0 && ($request->request->get('choice') === 'new'
          || $pendingOwnerId !== $request->request->get('configuration'))) {
          $error = 'Un fichier est déjà en attente. Terminez son traitement ou supprimez-le avant de changer de configuration.';
        } elseif ($request->request->get('choice') === 'new') {
          $label = trim((string) $request->request->get('label', ''));
          if ($label === '' || mb_strlen($label) > 255) {
            $error = 'Indiquez un nom de configuration de 1 à 255 caractères.';
          } else {
            $className = $repository->getClassName();
            $configuration = new $className();
            $configuration->setType($mappingConfigurationType);
            $configuration->setLibelle($label);
            $configuration->setActive(false);
            $this->em->persist($configuration);
            $this->em->flush();
            $this->mapping->setMappingConfigurationActive($configuration);
            return $this->redirectToRoute('check_format_mapping_page', ['code' => $code, 'configuration' => $configuration->getId()]);
          }
        } else {
          $configuration = $repository->findOneBy(['id' => $request->request->get('configuration'), 'type' => $mappingConfigurationType]);
          if (!$configuration instanceof MappingConfigurationInterface) {
            $error = 'Choisissez une configuration valide pour ce type d’import.';
          } else {
            $this->mapping->setMappingConfigurationActive($configuration);
            return $this->redirectToRoute('check_format_mapping_page', ['code' => $code, 'configuration' => $configuration->getId()]);
          }
        }
      }

      $selectedId = $request->query->get('configuration');
      if ($error !== null || !$active instanceof MappingConfigurationInterface || (string) $active->getId() !== $selectedId
        || (count($fichiersClient) > 0 && $pendingOwnerId !== $selectedId)) {
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/select_configuration_page.html.twig', [
          'basePath' => 'base.html.twig',
          'mapping_configuration_type' => $mappingConfigurationType,
          'mapping_configurations' => $configurations,
          'active_configuration' => $active,
          'pending_files' => count($fichiersClient),
          'pending_conflict' => $pendingConflict,
          'pending_owner_id' => $pendingOwnerId,
          'error' => $error,
        ]), $error !== null ? Response::HTTP_BAD_REQUEST : Response::HTTP_OK);
      }
      if (count($fichiersClient) == 1) {
        $fichiersClientFormatted = [];
        foreach ($fichiersClient as $fichier) {
          $fichiersClientFormatted[] = ['filename' => $fichier, 'basename' => basename($fichier)];
        }
        $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
        if ($mappingConfiguration instanceof MappingConfiguration) {
          $cuttingRules = $mappingConfiguration->getMappingConfigurationCuttingRules();
          $skippingRules = $mappingConfiguration->getMappingConfigurationSkippingRules();
        } else {
          $cuttingRules = [];
          $skippingRules = [];
        }

        if ($this->mapping->isXmlFile($fichiersClient[0])) {
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_xml_page.html.twig', [
            'basePath' => 'base.html.twig',
            'champs' => $this->mapping->getChampsPossiblesAIntegrer($code),
            'fichiers_en_attente' => $fichiersClientFormatted,
            'mapping_configuration_type' => $mappingConfigurationType,
            'xml_overview' => $this->mapping->getXmlFileOverview($fichiersClient[0]),
            'transformations' => TransformationEnum::getAvailableTransformationsWithLibelle(),
          ]));
        }

        $structure = $this->csvStructureWarning($active, $fichiersClient[0]);
        $accepted = $request->getSession()->get('_check_format_structure_confirmed', []);
        $acceptedFingerprint = is_array($accepted) ? ($accepted[(string) $active->getId()] ?? null) : null;
        if ($structure !== null && $acceptedFingerprint !== $structure['fingerprint']) {
          $confirmationError = null;
          if ($request->isMethod('POST') && $request->request->get('action') === 'confirm_structure') {
            $token = $request->request->get('_token');
            $fingerprint = $request->request->get('fingerprint');
            if ($this->csrfTokens->isTokenValid(new CsrfToken('check_format_structure_' . $code, $token))
              && is_string($fingerprint) && hash_equals($structure['fingerprint'], $fingerprint)) {
              $accepted = is_array($accepted) ? $accepted : [];
              $accepted[(string) $active->getId()] = $fingerprint;
              $request->getSession()->set('_check_format_structure_confirmed', $accepted);
              return $this->redirectToRoute('check_format_mapping_page', ['code' => $code, 'configuration' => $active->getId()]);
            }
            $confirmationError = 'Le fichier ou le mapping a changé, ou votre session a expiré. Vérifiez les différences avant de continuer.';
          }
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/csv_structure_warning.html.twig', [
            'basePath' => 'base.html.twig',
            'mapping_configuration_type' => $mappingConfigurationType,
            'configuration' => $active,
            'filename' => basename($fichiersClient[0]),
            'structure' => $structure,
            'confirmation_error' => $confirmationError,
          ]));
        }

        $data = $this->mapping->getFirstLinesFromFile($fichiersClient[0], 10, $cuttingRules, $skippingRules);
        $ligneEntete = $data['entete'];
        $lignes = $data['first_lines'];

        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_page.html.twig', [
          'basePath' => 'base.html.twig',
          'champs' => $this->mapping->getChampsPossiblesAIntegrer($code),
          'ligne_entete' => $ligneEntete,
          'lignes' => $lignes,
          'fichiers_en_attente' => $fichiersClientFormatted,
          'mapping_configuration_type' => $mappingConfigurationType
        ]));
      } else {
        $champsObligatoires = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy([
          'mappingConfigurationType' => $mappingConfigurationType, 'visible' => true, 'obligatoire' => true,
        ]);
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/no_fichier_to_map.html.twig', [
          'basePath' => 'base.html.twig',
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs_obligatoires' => $champsObligatoires,
          'selected_configuration' => $active,
          'mapping_structure' => MappingStructurePreview::build($active),
        ]));
      }
    } else {
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig', [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]));
    }
  }

  private function csvStructureWarning(MappingConfigurationInterface $configuration, string $file): ?array
  {
    if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'csv') {
      return null;
    }
    $expected = [];
    foreach ($configuration->getMappingConfigurationValues() as $value) {
      if ($value instanceof MappingConfigurationValueInterface && $value->getFichierIndex() !== null) {
        $index = (int) $value->getFichierIndex();
        if ($index >= 0) {
          $expected[$index] = $value->getFichierEntete();
        }
      }
    }
    if ($expected === []) {
      return null;
    }
    ksort($expected);
    $handle = fopen($file, 'r');
    if ($handle === false) {
      throw new \RuntimeException('Impossible de lire le fichier CSV.');
    }
    try {
      $header = fgetcsv($handle, 100000, ';', '"', '');
    } finally {
      fclose($handle);
    }
    $header = is_array($header) ? $header : [];
    $comparison = CsvStructureComparison::compare($expected, $header);
    if ($comparison === null) {
      return null;
    }
    $comparison['fingerprint'] = hash('sha256', serialize([$configuration->getId(), basename($file), hash_file('sha256', $file), $expected, $header]));
    return $comparison;
  }

  private function csvStructureRequiresConfirmation(MappingConfigurationInterface $configuration, Request $request): bool
  {
    $type = $configuration->getType();
    $files = glob($this->projectDir . $type->getFilesDirectory() . $type->getFilename() . '*') ?: [];
    if (count($files) !== 1) {
      return false;
    }
    $structure = $this->csvStructureWarning($configuration, $files[0]);
    if ($structure === null) {
      return false;
    }
    $accepted = $request->getSession()->get('_check_format_structure_confirmed', []);
    return !is_array($accepted) || ($accepted[(string) $configuration->getId()] ?? null) !== $structure['fingerprint'];
  }

  public function uploadFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
      if (!$mappingConfiguration instanceof MappingConfigurationInterface
        || (string) $mappingConfiguration->getId() !== $request->query->get('configuration')) {
        return new JsonResponse(['error_message' => 'La configuration active a changé. Revenez au choix du mapping avant de déposer le fichier.'], 409);
      }
      $pendingFiles = glob($this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*') ?: [];
      if (count($pendingFiles) > 0) {
        return new JsonResponse(['error_message' => 'Un fichier est déjà en attente pour ce type d’import.'], 409);
      }
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      if (!is_dir($dir)){
        mkdir($dir, 0775, true);
      }

      $files = $request->files->all();
      if (isset($files['file'])){
        $fichier = $files['file'];
        if ($fichier instanceof UploadedFile){

          try {
            $now = new DateTime();
            $extension = strtolower($fichier->getClientOriginalExtension());
            $newFileName = $mappingConfigurationType->getFilename().'_'.$now->format('YmdHis').'.'.$extension;

            $className = $this->em->getRepository(MappingConfigurationFileInterface::class)->getClassName();
            $mappingFile = new $className();
            if ($mappingFile instanceof MappingConfigurationFileInterface) {
              $mappingFile->setMappingConfiguration($mappingConfiguration);
              $mappingFile->setDateImport($now);
              $mappingFile->setInitialFilename($fichier->getClientOriginalName());
              $mappingFile->setFilename($newFileName);

              $params = $request->request->all();
              if (count($params) > 0){
                $mappingFile->setFormData(json_encode($params));
              }

              $this->em->persist($mappingFile);
              $this->em->flush();

              $fichier->move($dir, $newFileName);
              if (file_exists($dir.$newFileName)){
                chmod($dir.$newFileName, 0755);
                return new JsonResponse();
              } else {
                return new JsonResponse(['error_message' => "Une erreur est survenue lors de l\'envoi du fichier :( #2"], 500);
              }
            } else {
              return new JsonResponse(['error_message' => "Une erreur est survenue lors de l\'envoi du fichier :("], 500);
            }
          } catch (Exception $e){
            return new JsonResponse(['error_message' => "Une erreur est survenue lors de l\'envoi du fichier :( ". $e->getMessage()], 500);
          }
        } else {
          return new JsonResponse(['error_message' => 'Veuillez soumettre un fichier valide.'], 500);
        }
      } else {
        return new JsonResponse(['error_message' => 'Veuillez soumettre un fichier.'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Type de configuration introuvable :'.$code], 500);
    }
  }

  public function deleteFileAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $params = $request->request->all();
      $dir = $this->projectDir.$mappingConfigurationType->getFilesDirectory();
      foreach ($mappingConfigurationType->getMappingConfigurations() as $mappingConfiguration) {
        if ($mappingConfiguration instanceof MappingConfigurationInterface) {
          $mappingConfigurationFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy([
            'filename' => $params['filename'], 'mappingConfiguration' => $mappingConfiguration,
          ]);
          if ($mappingConfigurationFile instanceof MappingConfigurationFileInterface) {
            if (is_dir($dir)){
              if (file_exists($dir.'/'.$params['filename'])){
                try{
                  unlink($dir.'/'.$params['filename']);

                  $this->em->remove($mappingConfigurationFile);
                  $this->em->flush();
                  return new JsonResponse();
                } catch (Exception $e){
                  return new JsonResponse([], 500);
                }
              }
            }
          }
        }
      }

      if (is_dir($dir)){
        if (file_exists($dir.'/'.$params['filename'])) {
          try {
            unlink($dir . '/' . $params['filename']);
            return new JsonResponse();
          } catch (Exception $e){
            return new JsonResponse([], 500);
          }
        }
      }
    }
    return new JsonResponse([], 500);
  }

  public function controlePageAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
      if ($mappingConfiguration instanceof MappingConfigurationInterface) {
        if ($this->csvStructureRequiresConfirmation($mappingConfiguration, $request)) {
          return $this->redirectToRoute('check_format_mapping_page', ['code' => $code, 'configuration' => $mappingConfiguration->getId()]);
        }
        $valuesSaisiesManuelles = $this->mapping->getValueAvancesSaisieManuelleConfigurationMappingImport($mappingConfiguration);
        $fichiersClients = [];
        foreach (glob($this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*') as $path) {
          $mappingConfigurationFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy([
            'filename' =>  basename($path),
            'mappingConfiguration' => $mappingConfiguration
          ]);

          if (!($mappingConfigurationFile instanceof MappingConfigurationFileInterface)) {
            $className = $this->em->getRepository(MappingConfigurationFileInterface::class)->getClassName();
            $mappingConfigurationFile = new $className();
            if ($mappingConfigurationFile instanceof MappingConfigurationFileInterface) {
              $mappingConfigurationFile->setMappingConfiguration($mappingConfiguration);
              $mappingConfigurationFile->setDateImport((new DateTime())->setTimestamp(filemtime($path)));
              $mappingConfigurationFile->setInitialFilename(basename($path));
              $mappingConfigurationFile->setFilename(basename($path));
              $this->em->persist($mappingConfigurationFile);
            }
          }

          if ($mappingConfigurationFile instanceof MappingConfigurationFileInterface) {
            $fichiersClients[] = $mappingConfigurationFile;
          }
        }
        $this->em->flush();
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/controle.html.twig', [
          'mapping_configuration_type' => $mappingConfigurationType,
          'basePath' => 'base.html.twig',
          'fichiers_clients' => $fichiersClients,
          'advanced_values_saisie_manuelle' => $valuesSaisiesManuelles
        ]));
      } else {
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig', [
          'code' => $code,
          'basePath' => 'base.html.twig',
        ]));
      }
    } else {
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_type_not_found.html.twig', [
        'code' => $code,
        'basePath' => 'base.html.twig',
      ]));
    }
  }

  public function controlerFichierAction($code, Request $request)
  {
    ini_set('memory_limit', '512M');
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $mappingConfiguration = $this->getMappingConfigurationActive($mappingConfigurationType);
      if ($mappingConfiguration instanceof MappingConfigurationInterface) {
        if ($this->csvStructureRequiresConfirmation($mappingConfiguration, $request)) {
          return new JsonResponse(['error_message' => 'Vérifiez la structure du CSV sur la page de mapping avant le contrôle.'], 409);
        }
        $result = $this->mapping->controlerFichiers($mappingConfiguration, true);
        if ($result['error']) {
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/controle/controle_ko.html.twig', [
            'mapping_configuration_type' => $mappingConfigurationType,
            'resultat' => $result
          ]));
        } else {
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/controle/controle_ok.html.twig', [
            'mapping_configuration_type' => $mappingConfigurationType,
            'resultat' => $result
          ]));
        }
      } else {
        return new JsonResponse([], 500);
      }
    } else {
      return new JsonResponse([], 500);
    }
  }

  private function getMappingConfigurationActive(MappingConfigurationTypeInterface $mappingConfigurationType)
  {
    $mappingConfiguration = $this->em->getRepository(MappingConfigurationInterface::class)->findOneBy(['active' => true, 'type' => $mappingConfigurationType]);
    return $mappingConfiguration;
  }

  public function gererChampsPossiblesAction($code)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $champsPossibles = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType, 'visible' => true]);
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/gerer_champs_possibles.html.twig', [
        'mapping_configuration_type' => $mappingConfigurationType,
        'champsPossibles' => $champsPossibles,
      ]));
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function modelEditChampPossibleAction(Request $request)
  {
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/modals/edit_champ_possible.html.twig', [
        'champPossible' => $champPossible
      ]));
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function saveChampPossibleAction(Request $request)
  {
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      $champPossible->setLibelle($params['libelle']);
      $champPossible->setTable($params['table']);
      $champPossible->setType($params['type']);
      $champPossible->setObligatoire(isset($params['obligatoire']) && $params['obligatoire']);
      $champPossible->setNullable(isset($params['nullable']) && $params['nullable']);
      $champPossible->setIntegrationLocal(isset($params['integrationLocal']) && $params['integrationLocal']);
      $this->em->persist($champPossible);
      $this->em->flush();
      return new JsonResponse();
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function toggleBooleanValueChampPossibleAction(Request $request)
  {
    $params = $request->request->all();
    $champPossible = $this->em->getRepository(MappingChampPossibleInterface::class)->find($params['champ_id']);
    if ($champPossible instanceof MappingChampPossibleInterface){
      if ($params['type'] == 'obligatoire'){
        $res = !$champPossible->isObligatoire();
        $champPossible->setObligatoire(!$champPossible->isObligatoire());
      } elseif($params['type'] == 'nullable'){
        $res = !$champPossible->isNullable();
        $champPossible->setNullable(!$champPossible->isNullable());
      } elseif($params['type'] == 'integration-local'){
        $res = !$champPossible->isIntegrationLocal();
        $champPossible->setIntegrationLocal(!$champPossible->isIntegrationLocal());
      } else {
        return new JsonResponse([], 500);
      }
      $this->em->persist($champPossible);
      $this->em->flush();
      return new JsonResponse(['res' => $res]);
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function getMappingConfigurationSelectAction($codeMappingConfiguration)
  {
    $mappingTypeConfiguration = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $codeMappingConfiguration]);
    if ($mappingTypeConfiguration instanceof MappingConfigurationTypeInterface){
      $mappingConfigurations = $this->em->getRepository(MappingConfigurationInterface::class)->findBy(['type' => $mappingTypeConfiguration]);
      $pendingFiles = glob($this->projectDir . $mappingTypeConfiguration->getFilesDirectory() . $mappingTypeConfiguration->getFilename() . '*') ?: [];
      if (count($pendingFiles) > 0) {
        $active = $this->getMappingConfigurationActive($mappingTypeConfiguration);
        $mappingConfigurations = $active instanceof MappingConfigurationInterface ? [$active] : [];
      }
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_configuration_select.html.twig', [
        'mapping_configuration_type' => $mappingTypeConfiguration,
        'mapping_configurations' => $mappingConfigurations,
        'pending_files' => count($pendingFiles),
      ]));
    } else {
      return new JsonResponse([], 500);
    }
  }

  public function addMappingConfigurationAction($code, Request $request)
  {
    $params = $request->request->all();
    $files = $request->files->all();
    if (isset($params['mapping_configuration_libelle'])) {
      $libelle = $params['mapping_configuration_libelle'];
      $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
      if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
        $className = $this->em->getRepository(MappingConfigurationInterface::class)->getClassName();
        $configuration = new $className();
        if ($configuration instanceof MappingConfigurationInterface){
          $configuration->setType($mappingConfigurationType);
          $configuration->setLibelle($libelle);
          $configuration->setActive(true);

          $champsPossibles = $this->em->getRepository(MappingChampPossibleInterface::class)->findBy(['mappingConfigurationType' => $mappingConfigurationType]);
          $champsPossiblesCodes = [];
          foreach ($champsPossibles as $champPossible) {
            if ($champPossible instanceof MappingChampPossibleInterface) {
              $champsPossiblesCodes[] = $champPossible->getData();
            }
          }

          if (isset($files['file_import'])) {
            // mode importation
            $data = Yaml::parseFile($files['file_import']->getPathname());
            try {
              // Restore XML source options too: field transformations live in this JSON.
              if (($data['source_type'] ?? null) === MappingConfigurationInterface::SOURCE_TYPE_XML) {
                $sourceOptions = $data['source_options'] ?? [];
                foreach ($sourceOptions['field_mappings'] ?? [] as $key => $fieldMapping) {
                  $sourceOptions['field_mappings'][$key]['mapping_transformations'] = $this->normalizeXmlFieldTransformations($fieldMapping['mapping_transformations'] ?? []);
                }
                $configuration->setSourceType(MappingConfigurationInterface::SOURCE_TYPE_XML);
                $configuration->setSourceOptions($sourceOptions);
              }
              foreach ($data['values'] as &$importedValue) {
                foreach ($importedValue['transformations'] as &$importedTransformation) {
                  if (($importedTransformation['transformation'] ?? '') === TransformationEnum::DECIMAL_TO_INTEGER) {
                    $importedTransformation['transformation_options'] = DecimalToInteger::normalizeOptions($importedTransformation['transformation_options'] ?? null);
                  }
                }
                unset($importedTransformation);
              }
              unset($importedValue);
            } catch (TransformationException $exception) {
              return new JsonResponse(['error' => true, 'error_message' => $exception->getMessage()], 400);
            }
            foreach ($data['values'] as $_value) {
              if ($_value['mapping_code'] != '' && !in_array($_value['mapping_code'], $champsPossiblesCodes)) {
                return new JsonResponse([
                  'error' => true,
                  'error_message' => 'Un champs n\'existe pas ('. $_value['mapping_code'] .'), avez-vous ajouté les champs complémentaires ?'
                ], 500);
              }
              $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
              $value = new $className();
              $value->setMappingConfiguration($configuration);
              $value->setFichierEntete($_value['entete']);
              $value->setFichierIndex($_value['fichier_index']);
              $value->setMappingCode($_value['mapping_code']);
              $value->setMappingType($_value['type_mapping']);
              $this->em->persist($value);

              foreach ($_value['transformations'] as $_transformation) {
                $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
                $transformation = new $className();
                $transformation->setMappingConfigurationValue($value);
                $transformation->setNbCaract($_transformation['nb_caract']);
                $transformation->setTransformation($_transformation['transformation']);
                if (isset($_transformation['transformation_options']) && is_array($_transformation['transformation_options'])) {
                  $transformation->setTransformationOptions($_transformation['transformation_options']);
                }
                $this->em->persist($transformation);
              }
              foreach ($_value['translations'] as $_translation) {
                $className = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->getClassName();
                $translation = new $className();
                $translation->setMappingConfigurationValue($value);
                $translation->setValue($_translation['value']);
                $translation->setTranslation($_translation['translation']);
                $this->em->persist($translation);
              }
              foreach ($_value['values_avances'] as $_valueAvance) {
                $type = $this->em->getRepository(MappingConfigurationValueAvanceTypeInterface::class)->findOneBy(['code' => $_valueAvance['type']]);
                if ($type instanceof MappingConfigurationValueAvanceTypeInterface){
                  // on ajoute la value avancée
                  if ($type->getCode() == 'value_file') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'date_custom') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceDateCustomInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'multi_column_array') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceMultiColumnArrayInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'auto_increment') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceAutoIncrementInterface::class)->getClassName();
                  } elseif ($type->getCode() == 'saisie_manuelle') {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceSaisieManuelleInterface::class)->getClassName();
                  } else {
                    $className = $this->em->getRepository(MappingConfigurationValueAvanceTextInterface::class)->getClassName();
                  }
                  $valueAvance = new $className();
                  if ($valueAvance instanceof MappingConfigurationValueAvanceInterface) {
                    $valueAvance->initFromImportFileValueAvance($_valueAvance);
                    $valueAvance->setMappingConfigurationValueAvanceType($type);
                    $valueAvance->setMappingConfigurationValue($value);
                    $valueAvance->setOrdre($_valueAvance['order']);
                  }

                  $this->em->persist($valueAvance);
                } else {
                  return new JsonResponse(['error_message' => 'Une erreur est survenue. (2)'], 500);
                }
              }
            }
          }
          $this->em->persist($configuration);
          $this->em->flush();
          return new JsonResponse();
        } else {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'ajout de la configuration'], 500);
        }
      } else {
        return new JsonResponse(['error' => true, 'error_message' => 'Type de configuration introuvable !'], 500);

      }
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'ajout de la configuration'], 500);
  }

  public function exportMappingConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        $publicDir = $this->projectDir.'/public';
        $tmpDir = $publicDir.'/database/tmp_files';
        if (!is_dir($tmpDir)) {
          mkdir($tmpDir, '0755', true);
        }
        $filename = 'tmp_export_mapping_configuration_'.$configuration->getId();
        $filePath = $tmpDir.'/' . $filename . '.yaml';
        if (file_exists($filePath)) {
          unlink($filePath);
        }

        $dumpedData = Yaml::dump($configuration->getFormattedConfiguration(), 2, 4, Yaml::DUMP_MULTI_LINE_LITERAL_BLOCK);
        file_put_contents($filePath, $dumpedData);

        return new JsonResponse(['success' => true, 'url' => $this->generateUrl('check_format_mapping_download_export_configuration', ['filename' => $filename])]);
      } else {
        return new JsonResponse(['error_message' => 'La configuration n\'a pas été trouvé'], 500);
      }
    }
    return new JsonResponse(['error_message' => 'Un paramètre est manquant pour exporter la configuration.'], 500);
  }

  public function downloadExportMappingConfigurationAction($filename)
  {
    $filepath = $this->projectDir.'/public/database/tmp_files/'.$filename.'.yaml';
    if (file_exists($filepath)) {
      return $this->file($filepath);
    }
    throw new Exception('File not found');
  }

  public function importMappingConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    $files = $request->files->all();
    var_dump($params);
    var_dump($files);
    die;
  }

  public function removeMappingConfigurationValuesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        try {
          foreach ($configuration->getMappingConfigurationValues() as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              foreach ($value->getMappingConfigurationValueTranslations() as $translation) {
                $this->em->remove($translation);
              }
              $this->em->remove($value);
            }
          }
          $this->em->remove($configuration);
          $this->em->flush();
        } catch (Exception $e) {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration'], 500);
        }
        return new JsonResponse();
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la suppression de la configuration (paramètre manquant)'], 500);
  }

  public function addConfigurationValuesAvancesAction(Request $request, $id)
  {
    $params = $request->request->all();
    $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($id);
    if ($configuration instanceof MappingConfigurationInterface){
      $available = $this->mapping->getChampsAMapper($configuration->getId(), false);
      if ($available['error']) {
        return new JsonResponse(['error_message' => $available['error_message']], 500);
      }
      if (!isset($params['type']) || !is_string($params['type']) || !isset($available['champs_a_mapper'][$params['type']])) {
        return new JsonResponse(['error_message' => 'Ce champ est déjà associé ou n’est pas disponible.'], 409);
      }
      $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
      $configurationValue = new $className();
      if ($configurationValue instanceof MappingConfigurationValueInterface){
        $configurationValue->setMappingCode($params['type']);
        $configurationValue->setMappingConfiguration($configuration);
        $this->em->persist($configurationValue);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvances($configuration->getId());
      } else {
        return new JsonResponse(['error_message' => 'Impossible de créer la valeur.'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Configuration introuvable.'], 500);
    }
  }

  public function getMappingConfigurationValuesAvancesDetailAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['configuration_avance_id'])) {
      $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['configuration_avance_id']);
      if ($configurationValue instanceof MappingConfigurationValueInterface){
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
  }

  public function removeConfigurationValuesAvancesAction(Request $request)
  {
    $params = $request->request->all();
    $valueAvance = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_value_id']);
    if ($valueAvance instanceof MappingConfigurationValueInterface) {
      try {
        foreach ($valueAvance->getMappingConfigurationValueAvances() as $avance) {
          $this->em->remove($avance);
        }
        $this->em->remove($valueAvance);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvances($params['mapping_id']);
      } catch (Exception $e) {
        return new JsonResponse(['error_message' => 'Impossible de supprimer la configuration avancée : '.$e->getMessage()], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Impossible de supprimer la configuration avancée.'], 500);
    }
  }

  public function addConfigurationValueAvanceDetailAction(Request $request, $id)
  {
    $params = $request->request->all();
    $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($id);
    if ($configurationValue instanceof MappingConfigurationValueInterface){
      $type = $this->em->getRepository(MappingConfigurationValueAvanceTypeInterface::class)->findOneBy(['code' => $params['type']]);
      if ($type instanceof MappingConfigurationValueAvanceTypeInterface){

        // on ajoute la value avancée
        if ($type->getCode() == 'value_file') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceFileInterface){
            $value->setFichierEntete($params['file_entete']);
            $value->setFichierIndex($params['file_index']);
          }
        } elseif ($type->getCode() == 'date_custom') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceDateCustomInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceDateCustomInterface){
            $value->setFormat($params['date_custom_format']);
            $value->setModifier($params['date_custom_modify']);
          }
        } elseif ($type->getCode() == 'multi_column_array') {
          $className = $this->em->getRepository(MappingConfigurationValueAvanceMultiColumnArrayInterface::class)->getClassName();
          $value = new $className();
          if ($value instanceof MappingConfigurationValueAvanceMultiColumnArrayInterface){
            $value->setDelimiter($params['delimiter']);
            $value->setColumns($params['multi_column_array']);
          }
        } else {
          if (isset($params['value'])){
            $className = $this->em->getRepository(MappingConfigurationValueAvanceTextInterface::class)->getClassName();
            $value = new $className();
            if ($value instanceof MappingConfigurationValueAvanceTextInterface){
              $value->setValue($params['value']);
            }
          } else {
            return new JsonResponse(['error_message' => 'Impossible de récuperer la valeur saisie.'], 500);
          }
        }
        $value->setMappingConfigurationValue($configurationValue);
        $value->setMappingConfigurationValueAvanceType($type);
        $maxOrdre = 0;
        foreach ($configurationValue->getMappingConfigurationValueAvances() as $existingPart) {
          $maxOrdre = max($maxOrdre, $existingPart->getOrdre());
        }
        $value->setOrdre($maxOrdre + 1);
        $this->em->persist($value);
        $this->em->flush();

        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
      } else {
        return new JsonResponse(['error_message' => 'Une erreur est survenue. (2)'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue. (1)'], 500);
    }
  }

  public function updateConfigurationValueAvanceFilePaddingAction(Request $request)
  {
    $id = (string) $request->request->get('id');
    if (!$this->csrfTokens->isTokenValid(new CsrfToken('mapping_file_padding_'.$id, (string) $request->request->get('_token')))) {
      return new JsonResponse(['error_message' => 'Session expirée. Rechargez la page.'], 403);
    }
    $value = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->find($id);
    if (!$value instanceof MappingConfigurationValueAvanceFileInterface) {
      return new JsonResponse(['error_message' => 'Cette colonne ne prend pas en charge le remplissage.'], 400);
    }
    $rawLength = trim((string) $request->request->get('longueur', ''));
    if ($rawLength !== '' && (!ctype_digit($rawLength) || (int) $rawLength < 1 || (int) $rawLength > 255)) {
      return new JsonResponse(['error_message' => 'La longueur doit être comprise entre 1 et 255 caractères.'], 400);
    }
    $configurationValue = $value->getMappingConfigurationValue();
    $matchingParts = 0;
    foreach ($configurationValue->getMappingConfigurationValueAvances() as $part) {
      if ($part instanceof MappingConfigurationValueAvanceFileInterface
        && $part->getOrdre() === $value->getOrdre() && $part->getFichierIndex() === $value->getFichierIndex()) {
        $matchingParts++;
      }
    }
    if ($matchingParts !== 1) {
      return new JsonResponse(['error_message' => 'Colonnes ambiguës : supprimez et recréez cette partie du champ avancé.'], 400);
    }
    $existing = null;
    foreach ($configurationValue->getMappingConfigurationValueTransformations() as $transformation) {
      if ($transformation instanceof MappingConfigurationValueTransformationInterface
        && $transformation->getTransformation() === TransformationEnum::ADD_CHARACTER_ZERO_START
        && AdvancedPartTransformation::matches($transformation, $value)) {
        $existing = $transformation;
        break;
      }
    }
    if ($rawLength === '') {
      if ($existing !== null) {
        $this->em->remove($existing);
      }
    } else {
      if ($existing === null) {
        $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
        $existing = new $className();
        $existing->setMappingConfigurationValue($configurationValue);
        $existing->setTransformation(TransformationEnum::ADD_CHARACTER_ZERO_START);
      }
      $existing->setNbCaract($rawLength);
      $existing->setTransformationOptions(['advanced_part' => [
        'ordre' => $value->getOrdre(), 'fichier_index' => $value->getFichierIndex(),
      ]]);
      $this->em->persist($existing);
    }
    $this->em->flush();
    return new JsonResponse(['success' => true]);
  }

  public function addAdvancedPartTransformationAction(Request $request)
  {
    $id = (string) $request->request->get('id');
    if (!$this->csrfTokens->isTokenValid(new CsrfToken('mapping_file_padding_'.$id, (string) $request->request->get('_token')))) {
      return new JsonResponse(['error_message' => 'Session expirée. Rechargez la page.'], 403);
    }
    $part = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->find($id);
    if (!$part instanceof MappingConfigurationValueAvanceFileInterface) {
      return new JsonResponse(['error_message' => 'Colonne introuvable.'], 400);
    }
    $configurationValue = $part->getMappingConfigurationValue();
    $matchingParts = 0;
    foreach ($configurationValue->getMappingConfigurationValueAvances() as $candidate) {
      if ($candidate instanceof MappingConfigurationValueAvanceFileInterface
        && $candidate->getOrdre() === $part->getOrdre() && $candidate->getFichierIndex() === $part->getFichierIndex()) {
        $matchingParts++;
      }
    }
    if ($matchingParts !== 1) {
      return new JsonResponse(['error_message' => 'Colonnes ambiguës : supprimez et recréez cette partie du champ avancé.'], 400);
    }
    $code = (string) $request->request->get('transformation');
    if (!in_array($code, TransformationEnum::getAvailableTransformations(), true)) {
      return new JsonResponse(['error_message' => 'Transformation inconnue.'], 400);
    }
    $noLength = [TransformationEnum::REPLACE, TransformationEnum::DECIMAL_TO_INTEGER,
      TransformationEnum::INVERT_SIGN, TransformationEnum::REMOVE_CHARACTER_ZERO_START];
    $length = trim((string) $request->request->get('nb_caract', ''));
    if (!in_array($code, $noLength, true) && (!ctype_digit($length) || (int) $length < 1 || (int) $length > 255)) {
      return new JsonResponse(['error_message' => 'La longueur doit être comprise entre 1 et 255 caractères.'], 400);
    }
    $options = $request->request->all('transformation_options');
    try {
      if ($code === TransformationEnum::DECIMAL_TO_INTEGER) {
        $options = DecimalToInteger::normalizeOptions($options);
      } elseif ($code === TransformationEnum::REPLACE) {
        $options = ['search' => (string) ($options['search'] ?? ''), 'replace' => (string) ($options['replace'] ?? '')];
        if ($options['search'] === '') {
          return new JsonResponse(['error_message' => 'Indiquez la valeur à rechercher.'], 400);
        }
      } else {
        $options = [];
      }
    } catch (TransformationException $exception) {
      return new JsonResponse(['error_message' => $exception->getMessage()], 400);
    }
    $options['advanced_part'] = ['ordre' => $part->getOrdre(), 'fichier_index' => $part->getFichierIndex()];
    $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
    $transformation = new $className();
    $transformation->setMappingConfigurationValue($configurationValue);
    $transformation->setTransformation($code);
    $transformation->setNbCaract(in_array($code, $noLength, true) ? '0' : $length);
    $transformation->setTransformationOptions($options);
    $this->em->persist($transformation);
    $this->em->flush();
    return new JsonResponse(['success' => true]);
  }

  public function removeAdvancedPartTransformationAction(Request $request)
  {
    $id = (string) $request->request->get('id');
    if (!$this->csrfTokens->isTokenValid(new CsrfToken('mapping_file_padding_'.$id, (string) $request->request->get('_token')))) {
      return new JsonResponse(['error_message' => 'Session expirée. Rechargez la page.'], 403);
    }
    $part = $this->em->getRepository(MappingConfigurationValueAvanceFileInterface::class)->find($id);
    $transformation = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)
      ->find($request->request->get('transformation_id'));
    if (!$part instanceof MappingConfigurationValueAvanceFileInterface
      || !$transformation instanceof MappingConfigurationValueTransformationInterface
      || $transformation->getMappingConfigurationValue()->getId() !== $part->getMappingConfigurationValue()->getId()
      || !AdvancedPartTransformation::matches($transformation, $part)) {
      return new JsonResponse(['error_message' => 'Transformation introuvable pour cette colonne.'], 400);
    }
    $this->em->remove($transformation);
    $this->em->flush();
    return new JsonResponse(['success' => true]);
  }

  public function removeConfigurationValuesAvancesDetailAction(Request $request)
  {
    $params = $request->request->all();
    $valueAvance = $this->em->getRepository(MappingConfigurationValueAvanceInterface::class)->find($params['valeur_avancee_id']);
    if ($valueAvance instanceof MappingConfigurationValueAvanceInterface){
      try {
        $configurationValue = $valueAvance->getMappingConfigurationValue();
        if ($valueAvance instanceof MappingConfigurationValueAvanceFileInterface) {
          foreach ($configurationValue->getMappingConfigurationValueTransformations() as $transformation) {
            if ($transformation instanceof MappingConfigurationValueTransformationInterface
              && AdvancedPartTransformation::matches($transformation, $valueAvance)) {
              $this->em->remove($transformation);
            }
          }
        }
        $this->em->remove($valueAvance);
        $this->em->flush();
        return $this->mapping->showMappingConfigurationValuesAvancesDetail($configurationValue);
      } catch (Exception $e) {
        return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
      }
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
    }
  }

  public function mappingFichierClientSelectChampsAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if ($mappingConfigurationType instanceof MappingConfigurationTypeInterface){
      $params = $request->request->all();
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id'] ?? null);
      if (!$configuration instanceof MappingConfigurationInterface || $configuration->getType()->getId() !== $mappingConfigurationType->getId()) {
        return new JsonResponse(['error_message' => 'Configuration introuvable.'], 400);
      }
      $advancedValues = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy([
        'mappingConfiguration' => $configuration,
        'fichierIndex' => null,
      ]);
      $advancedCodes = [];
      foreach ($advancedValues as $value) {
        if ($value instanceof MappingConfigurationValueInterface && $value->getMappingCode() !== null) {
          $advancedCodes[$value->getMappingCode()] = true;
        }
      }
      $champs = array_filter(
        $this->mapping->getChampsPossiblesAIntegrer($mappingConfigurationType->getCode()),
        static fn(array $champ): bool => !isset($advancedCodes[$champ['data']])
      );
      return new Response($this->twig->render(
        '@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs.html.twig',
        [
          'mapping_configuration_type' => $mappingConfigurationType,
          'champs' => $champs,
          'lib_colonne' => $params['lib_colonne']
        ]
      ));
    } else {
      return new JsonResponse(['error_message' => 'Une erreur est survenue'], 500);
    }
  }

  public function mappingFichierClientSelectChampsOptionsAction($code, Request $request)
  {
    $params = $request->request->all();
    $champSelect = $this->mapping->getChampPossibleByCode($params['champ'], $code);
    if (!is_null($champSelect)) {
      switch ($champSelect['type']) {
        case 'date':
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_date.html.twig', [
            'lib_colonne' => $params['lib_colonne'],
          ]));
        case 'array':
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_array.html.twig', [
            'lib_colonne' => $params['lib_colonne'],
          ]));
        default:
          return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/partials/mapping_fichier_select_champs_options_default.html.twig', [
            'lib_colonne' => $params['lib_colonne'],
          ]));
      }
    } else {
      return new Response(['error_message' => 'Une erreur est survenue lors de la sélection du champ. Si le problème persiste, veuillez contacter un administrateur.'], 500);
    }
  }

  public function saveMappingConfigurationAction(Request $request)
  {
    $valuesToDelete = [];
    $translationsArr = [];
    $params = $request->request->all();
    if (isset($params['mapping_id']) && isset($params['mapping'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        if ($this->csvStructureRequiresConfirmation($configuration, $request)) {
          return new JsonResponse(['error_message' => 'La structure du CSV a changé. Revenez à la page de mapping pour vérifier les différences.'], 409);
        }
        $advancedValues = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy([
          'mappingConfiguration' => $configuration,
          'fichierIndex' => null,
        ]);
        $advancedCodes = [];
        foreach ($advancedValues as $value) {
          if ($value instanceof MappingConfigurationValueInterface && $value->getMappingCode() !== null) {
            $advancedCodes[$value->getMappingCode()] = true;
          }
        }
        foreach ($params['mapping'] as $mapping) {
          $code = $mapping['mapping_code'] ?? null;
          if ($code !== null && isset($advancedCodes[$code])) {
            $existing = $this->em->getRepository(MappingConfigurationValueInterface::class)->findOneBy([
              'mappingConfiguration' => $configuration,
              'fichierIndex' => $mapping['index'],
            ]);
            if (!$existing instanceof MappingConfigurationValueInterface || $existing->getMappingCode() !== $code) {
              return new JsonResponse(['error_message' => 'Ce champ est déjà utilisé en mode avancé.'], 409);
            }
          }
        }
        if ($this->mapping->setMappingConfigurationActive($configuration)) {
          $mappings = $params['mapping'];
          // on supprime les valeurs déjà en BDD
          $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
          foreach ($values as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              if (!is_null($value->getFichierIndex())) {
                $valuesToDelete[$value->getId()] = $value;
              }
            }
          }

          // on boucle sur toutes les lignes pour les ajouter
          foreach ($mappings as $mapping){
            // on recherche la ligne
            $configurationValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->findOneBy([
              'mappingConfiguration' => $configuration, 'fichierIndex' => $mapping['index'],
            ]);
            if ($configurationValue instanceof MappingConfigurationValueInterface) {
              unset($valuesToDelete[$configurationValue->getId()]); // on enlève de la liste à supprimer
            } else {
              $className = $this->em->getRepository(MappingConfigurationValueInterface::class)->getClassName();
              $configurationValue = new $className();
              if ($configurationValue instanceof MappingConfigurationValueInterface){
                $configurationValue->setFichierIndex($mapping['index']);
                $configurationValue->setMappingConfiguration($configuration);
              }
            }

            if (isset($mapping['mapping_code']) && $mapping['mapping_code'] != '') {
              $mapping_code = $mapping['mapping_code'];
            } else {
              $mapping_code = null;
            }
            if (isset($mapping['mapping_type'])) {
              $mapping_type = $mapping['mapping_type'];
            } else {
              $mapping_type = null;
            }
            $configurationValue->setFichierEntete($mapping['nom_entete']);
            $configurationValue->setMappingCode($mapping_code);
            $configurationValue->setMappingType($mapping_type);
            $this->em->persist($configurationValue);
          }

          // on supprimes les valeurs non retrouvées
          foreach ($valuesToDelete as $id => $valueToDelete) {
            if ($valueToDelete instanceof MappingConfigurationValueInterface) {
              foreach ($valueToDelete->getMappingConfigurationValueTranslations() as $translation) {
                $this->em->remove($translation);
              }
              foreach ($valueToDelete->getMappingConfigurationValueTransformations() as $transformation) {
                $this->em->remove($transformation);
              }
              $this->em->remove($valueToDelete);
            }
          }
          $this->em->flush();
          return new JsonResponse();
        }
        return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration (paramètre manquant)'], 500);
  }

  public function saveXmlSourceConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id']) && isset($params['row_xpath'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        $rowXpath = trim((string) $params['row_xpath']);
        if ($rowXpath === '') {
          return new JsonResponse(['error' => true, 'error_message' => 'Le XPath du noeud répétable est obligatoire.'], 400);
        }

        if (!$this->mapping->setMappingConfigurationActive($configuration)) {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
        }

        $sourceOptions = $configuration->getSourceOptions();
        if (!is_array($sourceOptions)) {
          $sourceOptions = [];
        }
        $sourceOptions['row_xpath'] = $rowXpath;

        $configuration->setSourceType(MappingConfigurationInterface::SOURCE_TYPE_XML);
        $configuration->setSourceOptions($sourceOptions);
        $this->em->persist($configuration);
        $this->em->flush();

        return new JsonResponse([
          'source_type' => $configuration->getSourceType(),
          'source_options' => $configuration->getSourceOptions(),
        ]);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration XML (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration XML (paramètre manquant)'], 500);
  }

  public function getXmlRowPreviewAction($code, Request $request)
  {
    $mappingConfigurationType = $this->em->getRepository(MappingConfigurationTypeInterface::class)->findOneBy(['code' => $code]);
    if (!($mappingConfigurationType instanceof MappingConfigurationTypeInterface)) {
      return new JsonResponse(['error' => true, 'error_message' => 'Type de configuration introuvable : ' . $code], 500);
    }

    $params = $request->request->all();
    $rowXpath = isset($params['row_xpath']) ? trim((string) $params['row_xpath']) : '';
    if ($rowXpath === '') {
      return new JsonResponse(['error' => true, 'error_message' => 'Le XPath du noeud répétable est obligatoire.'], 400);
    }

    $directory = $this->projectDir . $mappingConfigurationType->getFilesDirectory() . $mappingConfigurationType->getFilename() . '*';
    $fichiersClient = glob($directory);
    if (count($fichiersClient) !== 1 || !$this->mapping->isXmlFile($fichiersClient[0])) {
      return new JsonResponse(['error' => true, 'error_message' => 'Aucun fichier XML unique en attente de mapping.'], 500);
    }

    $preview = $this->mapping->getXmlRowsPreview($fichiersClient[0], $rowXpath);
    return new JsonResponse($preview, $preview['error'] ? 400 : 200);
  }

  public function getXmlFieldMappingsAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        if (!$this->mapping->setMappingConfigurationActive($configuration)) {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
        }

        $sourceOptions = $configuration->getSourceOptions();
        if (is_array($sourceOptions) && isset($sourceOptions['field_mappings']) && is_array($sourceOptions['field_mappings'])) {
          return new JsonResponse(array_values($sourceOptions['field_mappings']));
        }

        $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
        $valuesArray = [];
        foreach ($values as $value) {
          if ($value instanceof MappingConfigurationValueInterface && !is_null($value->getFichierIndex())) {
            $valuesArray[] = [
              'id' => $value->getId(),
              'mapping_code' => $value->getMappingCode(),
              'relative_xpath' => $value->getFichierEntete(),
              'source_config' => [
                'type' => 'xpath',
                'sources' => [
                  ['xpath' => $value->getFichierEntete()],
                ],
              ],
              'mapping_type' => $value->getMappingType(),
              'mapping_translations' => $value->getMappingCongurationValueTranslationsFormatted(),
              'mapping_transformations' => $value->getMappingCongurationValueTransformationsFormatted(),
            ];
          }
        }
        return new JsonResponse($valuesArray);
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration XML (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration XML (paramètre manquant)'], 500);
  }

  public function saveXmlFieldMappingsAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        if (!$this->mapping->setMappingConfigurationActive($configuration)) {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
        }

        $sourceOptions = $configuration->getSourceOptions();
        if (!is_array($sourceOptions) || !isset($sourceOptions['row_xpath']) || trim((string) $sourceOptions['row_xpath']) === '') {
          return new JsonResponse(['error' => true, 'error_message' => 'Veuillez enregistrer le XPath du noeud répétable avant de mapper les champs XML.'], 400);
        }

        $mappings = isset($params['mappings']) && is_array($params['mappings']) ? $params['mappings'] : [];
        $fieldMappings = [];
        foreach ($mappings as $mapping) {
          $mappingCode = isset($mapping['mapping_code']) ? trim((string) $mapping['mapping_code']) : '';
          $sourceConfig = $this->normalizeXmlFieldSourceConfig($mapping);
          if ($mappingCode === '' || is_null($sourceConfig)) {
            continue;
          }

          try {
            $transformations = $this->normalizeXmlFieldTransformations($mapping['mapping_transformations'] ?? []);
          } catch (TransformationException $exception) {
            return new JsonResponse(['error' => true, 'error_message' => $mappingCode . ' : ' . $exception->getMessage()], 400);
          }
          $fieldMappings[$mappingCode] = [
            'mapping_code' => $mappingCode,
            'mapping_type' => isset($mapping['mapping_type']) && $mapping['mapping_type'] !== '' ? $mapping['mapping_type'] : null,
            'source_config' => $sourceConfig,
            'mapping_translations' => $this->normalizeXmlFieldTranslations($mapping['mapping_translations'] ?? []),
            'mapping_transformations' => $transformations,
          ];
        }

        $configuration->setSourceType(MappingConfigurationInterface::SOURCE_TYPE_XML);
        $sourceOptions['field_mappings'] = $fieldMappings;
        $configuration->setSourceOptions($sourceOptions);
        $this->em->persist($configuration);
        $this->em->flush();

        return new JsonResponse();
      }
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement des champs XML (ID non trouvé)'], 500);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement des champs XML (paramètre manquant)'], 500);
  }

  private function normalizeXmlFieldSourceConfig(array $mapping): ?array
  {
    if (isset($mapping['source_config']) && is_array($mapping['source_config'])) {
      $sourceConfig = $mapping['source_config'];
    } else {
      $relativeXpath = isset($mapping['relative_xpath']) ? trim((string) $mapping['relative_xpath']) : '';
      $sourceConfig = [
        'type' => 'xpath',
        'sources' => [
          ['xpath' => $relativeXpath],
        ],
      ];
    }

    $type = isset($sourceConfig['type']) ? trim((string) $sourceConfig['type']) : 'xpath';
    if (!in_array($type, ['xpath', 'fixed', 'concat', 'advanced'])) {
      return null;
    }

    if ($type === 'fixed') {
      $value = isset($sourceConfig['value']) ? trim((string) $sourceConfig['value']) : '';
      if ($value === '') {
        return null;
      }
      return [
        'type' => 'fixed',
        'value' => $value,
      ];
    }

    if ($type === 'advanced') {
      $parts = [];
      if (isset($sourceConfig['parts']) && is_array($sourceConfig['parts'])) {
        foreach ($sourceConfig['parts'] as $part) {
          if (!is_array($part)) {
            continue;
          }
          $partType = isset($part['type']) ? trim((string) $part['type']) : 'xpath';
          if ($partType === 'fixed') {
            $value = isset($part['value']) ? (string) $part['value'] : '';
            if ($value !== '') {
              $parts[] = ['type' => 'fixed', 'value' => $value];
            }
          } else {
            $xpath = isset($part['xpath']) ? trim((string) $part['xpath']) : '';
            if ($xpath !== '') {
              $parts[] = ['type' => 'xpath', 'xpath' => $xpath];
            }
          }
        }
      }

      if (count($parts) === 0) {
        return null;
      }

      return [
        'type' => 'advanced',
        'parts' => $parts,
      ];
    }

    $sources = [];
    if (isset($sourceConfig['sources']) && is_array($sourceConfig['sources'])) {
      foreach ($sourceConfig['sources'] as $source) {
        if (is_array($source) && isset($source['xpath'])) {
          $xpath = trim((string) $source['xpath']);
          if ($xpath !== '') {
            $sources[] = ['xpath' => $xpath];
          }
        }
      }
    }

    if (count($sources) === 0) {
      return null;
    }

    if ($type === 'xpath') {
      return [
        'type' => 'xpath',
        'sources' => [$sources[0]],
      ];
    }

    return [
      'type' => 'concat',
      'separator' => isset($sourceConfig['separator']) ? (string) $sourceConfig['separator'] : '',
      'sources' => $sources,
    ];
  }

  private function normalizeXmlFieldTranslations($translations): array
  {
    if (!is_array($translations)) {
      return [];
    }

    $normalized = [];
    foreach ($translations as $translation) {
      if (!is_array($translation) || !isset($translation['value'])) {
        continue;
      }
      $value = (string) $translation['value'];
      if ($value === '') {
        continue;
      }
      $normalized[] = [
        'value' => $value,
        'translation' => isset($translation['translation']) && $translation['translation'] !== ''
          ? (string) $translation['translation']
          : null,
      ];
    }

    return $normalized;
  }

  private function normalizeXmlFieldTransformations($transformations): array
  {
    if (!is_array($transformations)) {
      return [];
    }

    $availableTransformations = TransformationEnum::getAvailableTransformations();
    $normalized = [];
    foreach ($transformations as $transformation) {
      if (!is_array($transformation) || !isset($transformation['transformation'])) {
        continue;
      }

      $transformationCode = (string) $transformation['transformation'];
      if (!in_array($transformationCode, $availableTransformations)) {
        continue;
      }

      $options = isset($transformation['transformation_options']) && is_array($transformation['transformation_options'])
      ? $transformation['transformation_options'] : null;
      if ($transformationCode === TransformationEnum::DECIMAL_TO_INTEGER) {
        $options = DecimalToInteger::normalizeOptions($options);
      }
      $normalized[] = [
        'transformation' => $transformationCode,
        'nb_caract' => isset($transformation['nb_caract']) ? (int) $transformation['nb_caract'] : 0,
        'transformation_options' => $options,
      ];
    }

    return $normalized;
  }

  public function showRecapMappingConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_recapitulatif.html.twig', [
          'config' => $configuration
        ]));
      }
    }
    return new JsonResponse([], 500);
  }

  public function showRecapDecoupageChampsMappingConfigurationAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface) {
        return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_show_cutting_rules.html.twig', [
          'config' => $configuration
        ]));
      }
    }
    return new JsonResponse([], 500);
  }

  public function getMappingConfigurationValuesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])){
      $configuration = $this->em->getRepository(MappingConfigurationInterface::class)->find($params['mapping_id']);
      if ($configuration instanceof MappingConfigurationInterface){
        if ($this->mapping->setMappingConfigurationActive($configuration)){
          $values = $this->em->getRepository(MappingConfigurationValueInterface::class)->findBy(['mappingConfiguration' => $configuration]);
          $valuesArray = [];
          foreach ($values as $value) {
            if ($value instanceof MappingConfigurationValueInterface) {
              array_push(
                $valuesArray,
                [
                  'fichier_index' => $value->getFichierIndex(),
                  'fichier_entete' => $value->getFichierEntete(),
                  'mapping_code' => $value->getMappingCode(),
                  'id' => $value->getId(),
                  'mapping_type' => $value->getMappingType(),
                  'mapping_translations' => $value->getMappingCongurationValueTranslationsFormatted(),
                  'mapping_transformations' => $value->getMappingCongurationValueTransformationsFormatted()
                ]
              );
            }
          }
          return new JsonResponse($valuesArray);
        } else {
          return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de l\'enregistrement de la configuration active'], 500);
        }
      } else {
        return new JsonResponse([
          'error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (ID non trouvé : '.$params['mapping_id'].')',
        ], 500);
      }
    } else {
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
    }
  }

  public function getMappingConfigurationValuesAvancesAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])){
      return $this->mapping->showMappingConfigurationValuesAvances($params['mapping_id']);
    } else {
      return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
    }
  }

  public function addTranslationAction(Request $request, $idValue)
  {
    $params = $request->request->all();
    $mappingValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($idValue);
    if ($mappingValue instanceof MappingConfigurationValueInterface){
      $className = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->getClassName();
      $translation = new $className();
      if ($translation instanceof MappingConfigurationValueTranslationInterface){
        $translation->setMappingConfigurationValue($mappingValue);
        $translation->setValue($params['value_fichier']);
        if ($params['translate_mode'] == 'set_traduction') {
          $translation->setTranslation($params['translation']);
        } else {
          $translation->setTranslation(null);
        }
        $this->em->persist($translation);
        $this->em->flush();
        return new JsonResponse(['id' => $translation->getId()]);
      }
    }
    return new JsonResponse([], 500);
  }

  public function removeTranslationAction(Request $request)
  {
    $params = $request->request->all();
    $translation = $this->em->getRepository(MappingConfigurationValueTranslationInterface::class)->find($params['translation_id']);
    if ($translation instanceof MappingConfigurationValueTranslationInterface) {
      try {
        $this->em->remove($translation);
        $this->em->flush();
        return new JsonResponse();
      } catch (Exception $exception) {
        return new JsonResponse([], 500);
      }
    }
    return new JsonResponse([], 500);
  }

  public function distinctValuesAction(int $id): JsonResponse
  {
    $value = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($id);
    if (!$value instanceof MappingConfigurationValueInterface) {
      return new JsonResponse(['status' => 'unavailable'], 404);
    }
    $configuration = $value->getMappingConfiguration();
    if (!$configuration instanceof MappingConfigurationInterface || !$configuration->isActive()) {
      return new JsonResponse(['status' => 'unavailable']);
    }
    $type = $configuration->getType();
    $files = glob($this->projectDir . $type->getFilesDirectory() . $type->getFilename() . '*') ?: [];
    if (count($files) !== 1) {
      return new JsonResponse(['status' => 'unavailable']);
    }
    $record = $this->em->getRepository(MappingConfigurationFileInterface::class)->findOneBy(['filename' => basename($files[0])]);
    if ($record instanceof MappingConfigurationFileInterface && $record->getMappingConfiguration()->getId() !== $configuration->getId()) {
      return new JsonResponse(['status' => 'unavailable']);
    }
    try {
      $data = $this->mapping->getDataFromMappingConfigurationFile($files[0], $configuration);
      if ($data['entete']) {
        array_shift($data['data']);
      }
      $values = (new DistinctMappingValues())->collect($value, $data['data']);
      return new JsonResponse(['status' => $values === null ? 'unavailable' : 'ok', 'values' => $values]);
    } catch (\Throwable $exception) {
      return new JsonResponse(['status' => 'unavailable']);
    }
  }

  public function updateTranslationsAction(Request $request)
  {
    $params = $request->request->all();
    $value = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_id']);
    if ($value instanceof MappingConfigurationValueInterface){
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_translations.html.twig', [
        'mapping_value' => $value
      ]));
    } else {
      return new JsonResponse(['error_message' => 'Impossible de trouver la configuration pour l\'id : '.$params['mapping_id'].'.'], 500);
    }
  }

  public function addTransformationAction(Request $request, $idValue)
  {
    $params = $request->request->all();
    $options = isset($params['transformation_options']) && is_array($params['transformation_options']) ? $params['transformation_options'] : null;
    if (($params['transformation'] ?? '') === TransformationEnum::DECIMAL_TO_INTEGER) {
      try {
        $options = DecimalToInteger::normalizeOptions($options);
      } catch (TransformationException $exception) {
        return new JsonResponse(['error_message' => $exception->getMessage()], 400);
      }
    }
    $mappingValue = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($idValue);
    if ($mappingValue instanceof MappingConfigurationValueInterface){
      $className = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->getClassName();
      $transformationValue = new $className();
      if ($transformationValue instanceof MappingConfigurationValueTransformationInterface){
        $transformationValue->setMappingConfigurationValue($mappingValue);
        $transformationValue->setTransformation($params['transformation']);
        $transformationValue->setNbCaract($params['nb_caractere']);
        if ($options !== null) {
          $transformationValue->setTransformationOptions($options);
        }
        $this->em->persist($transformationValue);
        $this->em->flush();
        return new JsonResponse(['id' => $transformationValue->getId(), 'options' => $options]);
      }
    }
    return new JsonResponse([], 500);
  }

  public function removeTransformationAction(Request $request)
  {
    $params = $request->request->all();
    $transformation = $this->em->getRepository(MappingConfigurationValueTransformationInterface::class)->find($params['transformation_id']);
    if ($transformation instanceof MappingConfigurationValueTransformationInterface) {
      try {
        $this->em->remove($transformation);
        $this->em->flush();
        return new JsonResponse();
      } catch (Exception $exception) {
        return new JsonResponse([], 500);
      }
    }
    return new JsonResponse([], 500);
  }

  public function updateTransformationsAction(Request $request)
  {
    $params = $request->request->all();
    $value = $this->em->getRepository(MappingConfigurationValueInterface::class)->find($params['mapping_id']);
    $transformations = TransformationEnum::getAvailableTransformationsWithLibelle();
    if ($value instanceof MappingConfigurationValueInterface) {
      return new Response($this->twig->render('@ImanagingCheckFormat/Mapping/mapping_configuration_transformations.html.twig', [
        'mapping_value' => $value,
        'transformations' => $transformations
      ]));
    } else {
      return new JsonResponse(['error_message' => 'Impossible de trouver la configuration pour l\'id : '.$params['mapping_id'].'.'], 500);
    }
  }

  public function getChampsObligatoiresAMapperAction(Request $request)
  {
    $params = $request->request->all();
    if (isset($params['mapping_id'])) {
      $res = $this->mapping->getChampsAMapper($params['mapping_id'], true);
      if ($res['error']) {
        return new JsonResponse($res, 500);
      }
      return new JsonResponse($res['champs_a_mapper'], 200);
    }
    return new JsonResponse(['error' => true, 'error_message' => 'Une erreur est survenue lors de la récupération de la configuration (paramètre manquant)'], 500);
  }

  public function saveSaisiesManuellesConfigurationFileAction($id, Request $request)
  {
    $mappingFile = $this->em->getRepository(MappingConfigurationFileInterface::class)->find($id);
    if ($mappingFile instanceof MappingConfigurationFileInterface) {
      $params = $request->request->all();
      foreach ($params as $id => $value) {
        $mappingValueAvance = $this->em->getRepository(MappingConfigurationValueAvanceSaisieManuelleInterface::class)->find($id);
        if ($mappingValueAvance instanceof MappingConfigurationValueAvanceSaisieManuelleInterface) {
          $mappingFile->setValueSaisieManuelle($mappingValueAvance->getId(), $value);
        }
      }
      $this->em->persist($mappingFile);
      $this->em->flush();

      return $this->redirectToRoute('check_format_mapping_controle_page', ['code' => $mappingFile->getMappingConfiguration()->getType()->getCode()]);
    } else {
      throw new Exception('Mapping file introuvable');
    }
  }
}
