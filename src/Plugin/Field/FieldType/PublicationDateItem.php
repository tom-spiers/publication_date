<?php

/**
 * @file
 * Contains \Drupal\publication_date\Plugin\Field\FieldType\PublicationDateItem.
 */

namespace Drupal\publication_date\Plugin\Field\FieldType;

use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Field\FieldStorageDefinitionInterface;
use Drupal\Core\Field\Plugin\Field\FieldType\ChangedItem;
use Drupal\Core\TypedData\DataDefinition;

/**
 * Defines the 'published_at' entity field type.
 *
 * Based on a field of this type, entity types can easily implement the
 * EntityChangedInterface.
 *
 * @FieldType(
 *   id = "published_at",
 *   label = @Translation("Publication date"),
 *   description = @Translation("An entity field containing a UNIX timestamp of when the entity has been last updated."),
 *   no_ui = TRUE,
 *   default_widget = "datetime_default",
 *   default_formatter = "timestamp",
 *   list_class = "\Drupal\Core\Field\ChangedFieldItemList"
 * )
 *
 * @see \Drupal\Core\Entity\EntityChangedInterface
 */
class PublicationDateItem extends ChangedItem {

  /**
   * {@inheritdoc}
   */
  public function applyDefaultValue($notify = TRUE) {
    parent::applyDefaultValue($notify);
    $value = $this->isPublished() ? REQUEST_TIME : NULL;
    $published_at_or_now = isset($value) ? $value : PUBLICATION_DATE_DEFAULT;
    $this->setValue(['value' => $value, 'published_at_or_now' => $published_at_or_now], $notify);
    return $this;
  }

  /**
   * @inheritDoc
   */
  public static function propertyDefinitions(FieldStorageDefinitionInterface $field_definition) {
    $properties = parent::propertyDefinitions($field_definition);

    $properties['published_at_or_now'] = DataDefinition::create('timestamp')
      ->setLabel(t('Published at or now'))
      ->setComputed(TRUE);

    return $properties;
  }

  /**
   * {@inheritdoc}
   */
  public function preSave() {
    if (!$this->isPublished() && !$this->value) {
      $this->value = PUBLICATION_DATE_DEFAULT;
    }

    // Set the timestamp to request time if it is not set.
    if (!$this->value) {
      $this->value = REQUEST_TIME;
    }
    else {
      // On an existing entity translation, the changed timestamp will only be
      // set to the request time automatically if at least one other field value
      // of the entity has changed. This detection does not run on new entities
      // and will be turned off if the changed timestamp is set manually before
      // save, for example during migrations or by using
      // \Drupal\content_translation\ContentTranslationMetadataWrapperInterface::setChangedTime().
      /** @var \Drupal\Core\Entity\ContentEntityInterface $entity */
      $entity = $this->getEntity();
      /** @var \Drupal\Core\Entity\ContentEntityInterface $original */
      $original = $entity->original;
      $langcode = $entity->language()->getId();
      if (!$entity->isNew() && $original->hasTranslation($langcode)) {
        $original_value = $original->getTranslation($langcode)->get($this->getFieldDefinition()->getName())->value;
        if ($this->value == $original_value && $entity->hasTranslationChanges()) {
          $this->value = REQUEST_TIME;
        }
      }
    }
  }

  protected function isPublished() {
    $entity = $this->getEntity();
    if (!($entity instanceof FieldableEntityInterface && $entity->hasField('status'))) {
      return FALSE;
    }

    return $entity->get('status')->value;
  }

  /**
   * @inheritDoc
   */
  public function isEmpty() {
    if (isset($this->value) || isset($this->published_at_or_now)) {
      return FALSE;
    }
    return TRUE;
  }

}