<?php

namespace Drupal\custom_contact_api\Plugin\rest\resource;

use Drupal\contact\Entity\Message;
use Drupal\contact\MailHandlerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a resource for submitting contact forms.
 *
 * @RestResource(
 *   id = "contact_form_submit",
 *   label = @Translation("Contact Form Submit"),
 *   uri_paths = {
 *     "create" = "/api/contact-user"
 *   }
 * )
 */
class ContactFormResource extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The currently authenticated user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The contact mail handler service.
   *
   * @var \Drupal\contact\MailHandlerInterface
   */
  protected $mailHandler;

  /**
   * Constructs a ContactFormResource instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param array $serializer_formats
   *   The available serialization formats.
   * @param \Psr\Log\LoggerInterface $logger
   *   A logger instance.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The currently authenticated user.
   * @param \Drupal\contact\MailHandlerInterface $mail_handler
   *   The contact mail handler service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, AccountInterface $current_user, MailHandlerInterface $mail_handler)
  {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->currentUser = $current_user;
    $this->mailHandler = $mail_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition)
  {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('current_user'),
      $container->get('contact.mail_handler'),
    );
  }

  /**
   * Responds to POST requests.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *  Represents an HTTP request.
   *
   * @return ResourceResponse
   *
   * @throws \Symfony\Component\HttpFoundation\Response
   *  When invalid data passed or server error.
   */
  public function post(Request $request) {
    $data = json_decode($request->getContent(), TRUE);

    // For invalid inputs respond with cannot process your request message.
    if (empty($data['subject']) || empty($data['message'])) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid input, subject and email details missing.');
    }
    if (!$this->entityTypeManager->getStorage('user')->load($data['recipient'])) {
      throw new HttpException(Response::HTTP_UNPROCESSABLE_ENTITY, 'Invalid input, recipient does not exists.');
    }

    // Create the message entity using the contact form.
    $message = Message::create([
      'contact_form' => 'personal',
      'name' => $this->currentUser->getAccountName(),
      'mail' => $this->currentUser->getEmail(),
      'message' => $data['message'],
      'subject' => $data['subject'],
      'recipient' => $data['recipient'], // Recipient's user-id.
      'copy' =>  isset($data['copy']) ? 1 : 0, // Send yourself a copy of the email.
    ]);
    $message->save();

    // Send Email.
    try {
      $this->mailHandler->sendMailMessages($message, $this->currentUser);
    }
    catch (\Exception $e) {
      throw new HttpException(Response::HTTP_INTERNAL_SERVER_ERROR, 'Email could not be sent. Please try again later.');
    }

    return new ResourceResponse(['message' => 'Contact form submitted successfully']);
  }
}
