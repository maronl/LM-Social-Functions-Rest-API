<?php

namespace LM\WPPostLikeRestApi\Request;

/**
 * Created by PhpStorm.
 * User: maronl
 * Date: 25/10/17
 * Time: 11:32
 */
class LMWallPostsPictureUpdateRequest
{

    private $errors;

    function __construct()
    {
        $this->errors = array();
    }

    public function validateRequest(\WP_REST_Request $request)
    {
        $files = $request->get_file_params();

        if (!array_key_exists('picture', $files)) {
            $this->errors['picture'] = 'Nessun file caricato';
            return $this->errors;
        }

        $file = $files['picture'];

        $this->checkUploadErrors($file);
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->checkFileType($file);
        if (!empty($this->errors)) {
            return $this->errors;
        }

        $this->checkFileSize($file);
        if (!empty($this->errors)) {
            return $this->errors;
        }

        return $this->errors;
    }

    private function checkUploadErrors($file)
    {
        $phpFileUploadErrors = array(
            0 => 'There is no error, the file uploaded with success',
            1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
            2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
            3 => 'The uploaded file was only partially uploaded',
            4 => 'No file was uploaded',
            6 => 'Missing a temporary folder',
            7 => 'Failed to write file to disk.',
            8 => 'A PHP extension stopped the file upload.',
        );

        if ($file['error'] !== 0 && array_key_exists($file['error'], $phpFileUploadErrors)) {
            $this->errors['picture'] = $phpFileUploadErrors[$file['error']];
        } elseif ($file['error'] !== 0) {
            $this->errors['picture'] = 'Errore nel caricamento del file';
        }
    }

    private function checkFileType($file)
    {
        $allowedMimeType = array('image/gif', 'image/jpg', 'image/jpeg', 'image/png');
        if (!in_array($file['type'], $allowedMimeType)) {
            $this->errors['picture'] = 'Non sono ammessi file di tipo "' . $file['type'] . '". File validi sono: ' . implode(',',
                    $allowedMimeType);
        }
    }

    private function checkFileSize($file)
    {
        //maxSize = 15M
        $max = 15 * 1024 * 1024;
        if ($file['size'] > $max) {
            $this->errors['picture'] = 'Il file caricato è troppo grande. il limite è di 15Mb';
        }
    }

}