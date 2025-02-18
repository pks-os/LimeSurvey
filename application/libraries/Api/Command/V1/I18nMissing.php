<?php

namespace LimeSurvey\Libraries\Api\Command\V1;

use LimeSurvey\Models\Services\TranslationMoToJson;
use Permission;
use LimeSurvey\Api\Command\{
    CommandInterface,
    Request\Request,
    Response\Response,
    Response\ResponseFactory
};
use LimeSurvey\Api\Command\Mixin\Auth\AuthPermissionTrait;

class I18nMissing implements CommandInterface
{
    use AuthPermissionTrait;

    protected ResponseFactory $responseFactory;
    protected Permission $permission;

    /**
     * Constructor
     *
     * @param ResponseFactory $responseFactory
     * @param Permission $permission
     */
    public function __construct(
        ResponseFactory $responseFactory,
        Permission $permission
    ) {
        $this->responseFactory = $responseFactory;
        $this->permission = $permission;
    }

    /**
     * Handle missing translations
     * This will get an array of missing strings which need translation.
     * Those will be written in the file /application/helpers/newEditorTranslations
     * as gT('example');
     * The function checks if each string already exists in the file to avoid duplicates.
     *
     * @SuppressWarnings(PHPMD.ExcessiveMethodLength)
     * @param Request $request
     * @return Response
     */
    public function run(Request $request)
    {
        $keys = $request->getData('keys');

        if (empty($keys) || !is_array($keys)) {
            return $this->responseFactory
                ->makeError('Missing or invalid translation keys');
        }

        $filename = '/helpers/newEditorTranslations.php';
        $absolutePath = App()->getBasePath() . $filename;

        $updatedKeys = [];
        $existingKeys = [];
        App()->setLanguage('de');
        $transLateService = new TranslationMoToJson('de');
        $translations = $transLateService->translateMoToJson();
        if (!file_exists($absolutePath)) {
            // Create new file with header
            $content = "<?php\n// Translation-source for new editor. \n// Updated on " . date('Y-m-d H:i:s') . "\n\n";
            file_put_contents($absolutePath, $content);
        }

        // Read existing content
        $content = file_get_contents($absolutePath);

        foreach ($keys as $key) {
            if (empty($key)) {
                continue;
            }
            // check if key is also a key in array translations, then we can skip it as false positive
            if (is_array($translations) && array_key_exists($key, $translations)) {
                $existingKeys[] = $key;
                continue;
            }

            $newLine = "gT('" . addslashes($key) . "');";

            // Check if the key already exists
            if (strpos($content, $newLine) === false) {
                // Append new line to the file
                $content = rtrim($content, "\n") . "\n" . $newLine;
                $updatedKeys[] = $key;
            } else {
                $existingKeys[] = $key;
            }
        }

        if (!empty($updatedKeys)) {
            // Update the timestamp
            $content = preg_replace(
                '/\/\/ Updated on .*/',
                '// Updated on ' . date('Y-m-d H:i:s'),
                $content
            );

            file_put_contents($absolutePath, $content);
        }

        $message = '';
        if (!empty($updatedKeys)) {
            $message .= "Translation keys saved: " . implode(', ', $updatedKeys) . ". ";
        }
        if (!empty($existingKeys)) {
            $message .= "Translation keys already exist: " . implode(', ', $existingKeys) . ".";
        }

        return $this->responseFactory
            ->makeSuccess(['message' => $message]);
    }
}
