<?php
/***************************************************************************
 *                                                                          *
 *   (c) 2004 Vladimir V. Kalynyak, Alexey V. Vinokurov, Ilya M. Shalnev    *
 *                                                                          *
 * This  is  commercial  software,  only  users  who have purchased a valid *
 * license  and  accept  to the terms of the  License Agreement can install *
 * and use this program.                                                    *
 *                                                                          *
 ****************************************************************************
 * PLEASE READ THE FULL TEXT  OF THE SOFTWARE  LICENSE   AGREEMENT  IN  THE *
 * "copyright.txt" FILE PROVIDED WITH THIS DISTRIBUTION PACKAGE.            *
 ****************************************************************************/

namespace Tygh\UpgradeCenter\Validators;

use Tygh\Registry;

/**
 * Upgrade validators: Check file permissions
 * @todo Remove using of global functions
 */
class Permissions implements IValidator
{
    /**
     * Global App config
     *
     * @var array $config
     */
    protected $config = array();

    /**
     * Validator identifier
     *
     * @var array $name ID
     */
    protected $name = 'permissions';

    /**
     * FTP connection flag
     *
     * @var bool $ftp_connection_status true/false if Validator tried to connect
     */
    protected $ftp_connection_status = null;

    /**
     * Validate specified data by schema
     *
     * @param  array $schema  Incoming validator schema
     * @param  array $request Request data
     * @return array Validation result and Data to be displayed
     */
    public function check($schema, $request)
    {
        $data = array();
        $show_notifications = !empty($request['change_ftp_settings']);

        if (!empty($schema['files'])) {
            foreach ($schema['files'] as $file_path => $file_data) {
                $result = true;
                $original_path = $this->config['dir']['root'] . '/' . $file_path;

                switch ($file_data['status']) {
                    case 'changed':
                        if (file_exists($original_path)) {
                            if (!is_writable($original_path)) {
                                $result = $this->correctPermissions($original_path, $show_notifications);
                            }
                        } else {
                            $file_path = $this->getParentDir($file_path);

                            if (!is_writable($this->config['dir']['root'] . '/' . $file_path)) {
                                $result = $this->correctPermissions($original_path, $show_notifications);
                            }
                        }

                        break;

                    case 'deleted':
                        if (file_exists($original_path)) {
                            if (!is_writable($original_path)) {
                                $result = $this->correctPermissions($original_path, $show_notifications);
                            }
                        }

                        if ($result) {
                            $file_path = $this->getParentDir($file_path);

                            if (!is_writable($this->config['dir']['root'] . '/' . $file_path)) {
                                $result = $this->correctPermissions($this->config['dir']['root'] . '/' . $file_path, $show_notifications);
                            }
                        }

                        break;

                    case 'new':
                        if (file_exists($original_path) && !is_writable($original_path)) {
                            $result = $this->correctPermissions($original_path, $show_notifications);
                            $file_path = dirname($file_path);
                        } else {
                            $file_path = $this->getParentDir($file_path);

                            if (!is_writable($this->config['dir']['root'] . '/' . $file_path)) {
                                $result = $this->correctPermissions($this->config['dir']['root'] . '/' . $file_path, $show_notifications);
                            }
                        }
                        break;
                }

                if (!$result) {
                    $data[] = $file_path;
                }
            }
        }

        // Exclude duplicates
        $data = array_unique($data);

        return array(empty($data), $data);
    }

    /**
     * Gets validator name (ID)
     *
     * @return string Name
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * Sets writable permissions to the specified file/dir
     *
     * @param  string $path               Path to file/dir
     * @param  bool   $show_notifications Show FTP connection error notifications
     * @return bool   true if permissions were succesfully changed, false otherwise
     */
    public function correctPermissions($path, $show_notifications)
    {
        $ftp_link = Registry::get('ftp_connection');

        if (empty($ftp_link) && is_null($this->ftp_connection_status)) {
            if (fn_ftp_connect(Registry::get('settings.Upgrade_center'), $show_notifications)) {
                $this->ftp_connection_status = true;
            } else {
                $this->ftp_connection_status = false;
            }
        }

        $correction_result = true;

        if (is_file($path) || is_dir($path)) {
            if (!is_writable($path)) {
                @chmod($path, 0777);
            }
            if (!is_writable($path) && !is_null($this->ftp_connection_status)) {
                fn_ftp_chmod_file($path, 0777);
            }

            if (!is_writable($path)) {
                $correction_result = false;
            }
        }

        return $correction_result;
    }

    /**
     * Gets parent directory of the specified file/dir
     *
     * @param  string $path Path to file/dir
     * @return string Path to parent directory
     */
    protected function getParentDir($path)
    {
        $original_path = $this->config['dir']['root'] . '/' . $path;

        do {
            $old_path = $path;
            $path = dirname($path);

            $original_path = $this->config['dir']['root'] . '/' . $path;

            if (is_dir($original_path)) {
                break;
            }

        } while ($path != $old_path);

        return $path;
    }

    public function __construct()
    {
        $this->config = Registry::get('config');
    }
}
