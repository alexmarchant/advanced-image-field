<?php
/**
 * Advanced Image Field plugin for Craft 3.0
 * @copyright 2017 Alex Marchant
 */

namespace alexmarchant\advancedimage\fields;

use Craft;
use craft\fields\Assets;
use craft\base\ElementInterface;
use craft\helpers\Assets as AssetsHelper;
use craft\web\UploadedFile;

use yii\db\Schema;

class AdvancedImageField extends Assets
{
    // Static
    // =========================================================================

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('advanced-image-field', 'Advanced Image');
    }

    /**
     * @inheritdoc
     */
    public static function defaultSelectionLabel(): string
    {
        return Craft::t('advanced-image-field', 'Add an image');
    }

    // Properties
    // =========================================================================

    /**
     * @var bool|null Whether the image should be restricted to
     * [[minWidth]], [[maxWidth]], [[minHeight]], [[maxHeight]]
     */
    public $restrictDimensions;

    /**
     * @var int|null Min width of images (only used if [[restrictDimensions]] is true)
     */
    public $minWidth;

    /**
     * @var int|null Max width of images (only used if [[restrictDimensions]] is true)
     */
    public $maxWidth;

    /**
     * @var int|null Min height of images (only used if [[restrictDimensions]] is true)
     */
    public $minHeight;

    /**
     * @var int|null Max height of images (only used if [[restrictDimensions]] is true)
     */
    public $maxHeight;

    /**
     * @var bool|null Whether the image should be restricted to certain [[allowedImageTypes]]
     */
    public $restrictImageTypes;

    /**
     * @var array|null The file kinds that the field should be restricted to (only used if [[restrictImageTypes]] is true)
     */
    public $allowedImageTypes;

    // Public Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        $this->restrictFiles = true;
        $this->allowedKinds = ["image"];
        $this->inputTemplate = 'advanced-image-field/_input';
        $this->settingsTemplate = 'advanced-image-field/_settings';
    }

    /**
     * @inheritdoc
     */
    public function getElementValidationRules(): array
    {
        $rules = parent::getElementValidationRules();
        $rules[] = 'validateDimensions';
        $rules[] = 'validateImageTypes';
        return $rules;
    }

    /**
     * Validates the files to make sure they are of the allowed resolution.
     *
     * @param ElementInterface $element
     *
     * @return void
     */
    public function validateDimensions(ElementInterface $element)
    {
        if (!$this->restrictDimensions) {
            return;
        }

        foreach ($element->getFieldValue($this->handle)->all() as $asset) {
            if ($this->minWidth && $asset->width < $this->minWidth) {
                $element->addError(
                    $this->handle,
                    Craft::t(
                        'advanced-image-field',
                        '"{filename}" is {width}px wide, which is less than the minimum width of {minWidth}px.',
                        [
                            'filename' => $asset->filename,
                            'width' => $asset->width,
                            'minWidth' => $this->minWidth,
                        ]
                    )
                );
            }

            if ($this->maxWidth && $asset->width > $this->maxWidth) {
                $element->addError(
                    $this->handle,
                    Craft::t(
                        'advanced-image-field',
                        '"{filename}" is {width}px wide, which is greater than the maximum width of {maxWidth}px.',
                        [
                            'filename' => $asset->filename,
                            'width' => $asset->width,
                            'maxWidth' => $this->maxWidth,
                        ]
                    )
                );
            }

            if ($this->minHeight && $asset->height < $this->minHeight) {
                $element->addError(
                    $this->handle,
                    Craft::t(
                        'advanced-image-field',
                        '"{filename}" is {height}px high, which is less than the minimum height of {minHeight}px.',
                        [
                            'filename' => $asset->filename,
                            'height' => $asset->height,
                            'minHeight' => $this->minHeight,
                        ]
                    )
                );
            }

            if ($this->maxHeight && $asset->height > $this->maxHeight) {
                $element->addError(
                    $this->handle,
                    Craft::t(
                        'advanced-image-field',
                        '"{filename}" is {height}px high, which is greater than the maximum height of {maxHeight}px.',
                        [
                            'filename' => $asset->filename,
                            'height' => $asset->height,
                            'maxHeight' => $this->maxHeight,
                        ]
                    )
                );
            }
        }
    }

    /**
     * Validates the files to make sure they are of the allowed resolution.
     *
     * @param ElementInterface $element
     *
     * @return void
     */
    public function validateImageTypes(ElementInterface $element)
    {
        if (!$this->restrictImageTypes) {
            return;
        }

        $filenames = [];

        // Get all the value's assets' filenames
        /** @var Element $element */
        /** @var AssetQuery $value */
        $value = $element->getFieldValue($this->handle);
        foreach ($value->all() as $asset) {
            /** @var Asset $asset */
            $filenames[] = $asset->filename;
        }

        // Get any uploaded filenames
        $uploadedFiles = $this->_getUploadedFiles($element);
        foreach ($uploadedFiles as $file) {
            $filenames[] = $file['filename'];
        }

        // Now make sure that they all check out
        foreach ($filenames as $filename) {
            $fileExtension = mb_strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            if (!in_array($fileExtension, $this->allowedImageTypes, true)) {
                $element->addError(
                    $this->handle,
                    Craft::t('advanced-image-field', '"{fileExtension}" is not an allowed image type.', [
                        'fileExtension' => $fileExtension
                    ])
                );
            }
        }
    }

    public function imageTypeOptions(): array
    {
        return array_map(function ($extension) {
            return [
                'label' => $extension,
                'value' => $extension,
            ];
        }, AssetsHelper::getFileKinds()['image']['extensions']);
    }

    // Protected Methods
    // =========================================================================

    /**
     * @inheritdoc
     */
    protected function inputTemplateVariables($value = null, ElementInterface $element = null): array
    {
        $variables = parent::inputTemplateVariables($value, $element);
        $variables['field'] = $this;
        return $variables;
    }

    // Private Methods
    // =========================================================================

    /**
     * Returns any files that were uploaded to the field.
     *
     * @param ElementInterface $element
     *
     * @return array
     */
    private function _getUploadedFiles(ElementInterface $element): array
    {
        /** @var Element $element */
        $uploadedFiles = [];
        /** @var AssetQuery $query */
        $query = $element->getFieldValue($this->handle);
        $value = !empty($query->id) ? $query->id : [];
        // Grab data strings
        if (isset($value['data']) && is_array($value['data'])) {
            foreach ($value['data'] as $index => $dataString) {
                if (preg_match('/^data:(?<type>[a-z0-9]+\/[a-z0-9]+);base64,(?<data>.+)/i',
                    $dataString, $matches)) {
                    $type = $matches['type'];
                    $data = base64_decode($matches['data']);
                    if (!$data) {
                        continue;
                    }
                    if (!empty($value['filenames'][$index])) {
                        $filename = $value['filenames'][$index];
                    } else {
                        $extensions = FileHelper::getExtensionsByMimeType($type);
                        if (empty($extensions)) {
                            continue;
                        }
                        $filename = 'Uploaded_file.'.reset($extensions);
                    }
                    $uploadedFiles[] = [
                        'filename' => $filename,
                        'data' => $data,
                        'type' => 'data'
                    ];
                }
            }
        }
        // Remove these so they don't interfere.
        unset($value['data'], $value['filenames']);
        // See if we have uploaded file(s).
        $paramName = $this->requestParamName($element);
        if ($paramName !== null) {
            $files = UploadedFile::getInstancesByName($paramName);
            foreach ($files as $file) {
                $uploadedFiles[] = [
                    'filename' => $file->name,
                    'location' => $file->tempName,
                    'type' => 'upload'
                ];
            }
        }
        return $uploadedFiles;
    }
}

