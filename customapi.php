<?php
/**
 * @package     Joomla.Plugin
 * @subpackage  System.customapi
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

class PlgSystemCustomapi extends CMSPlugin
{
    protected $app;

    private $fileFields = ['photo', 'passport_file', 'other_document1', 'other_document2', 'other_document3'];
    private $filePaths = [
        'tbl_reg_delegate_v1' => '/var/www/html/abc_files/country/',
        'tbl_reg_honorary_v1' => '/var/www/html/abc_files/honorary/',
        'tbl_reg_business_v1' => '/var/www/html/abc_files/business/',
        'tbl_reg_general_v1' => '/var/www/html/abc_files/general/'
    ];

    private $fileSubPaths = [
        'photo' => 'photo/',
        'passport_file' => 'passport/',
        'other_document1' => 'other1/',
        'other_document2' => 'other2/',
        'other_document3' => 'other3/'
    ];

    public function onAfterInitialise()
    {
        $input = Factory::getApplication()->input;
        $option = $input->get('option', '', 'cmd');
        $apiKey = $this->params->get('api_key');

        if ($option === 'com_customapi')
        {
            $providedApiKey = $input->get('api_key', '', 'string');
            $tableName = $input->get('table', '', 'string');
            $fields = $input->get('fields', '*', 'string');
            $format = $input->get('format', 'json', 'string');

            if ($apiKey !== $providedApiKey)
            {
                $this->outputResponse(null, JText::_('JERROR_ALERTNOAUTHOR'), $format);
                Factory::getApplication()->close();
            }

            if (empty($tableName))
            {
                $this->outputResponse(null, JText::_('JERROR_TABLE_NAME_NOT_PROVIDED'), $format);
                Factory::getApplication()->close();
            }

            try
            {
                $db = Factory::getDbo();

                if ($fields === '*') {
                    $columnsQuery = $db->getQuery(true)
                        ->select('COLUMN_NAME')
                        ->from('INFORMATION_SCHEMA.COLUMNS')
                        ->where('TABLE_SCHEMA = DATABASE()')
                        ->where('TABLE_NAME = ' . $db->quote($tableName));

                    $db->setQuery($columnsQuery);
                    $columns = $db->loadColumn();
                    $fields = implode(',', array_map([$db, 'quoteName'], $columns));
                }

                $query = $db->getQuery(true)
                    ->select($fields)
                    ->from($db->quoteName($tableName));

                $db->setQuery($query);
                $results = $db->loadAssocList();

                // Process the results to append URLs to file fields
                $results = $this->processFileFields($results, $tableName);

                $this->outputResponse($results, null, $format);
            }
            catch (Exception $e)
            {
                $this->outputResponse(null, $e->getMessage(), $format);
            }

            Factory::getApplication()->close();
        }
    }

    private function processFileFields($results, $tableName)
    {
        if (isset($this->filePaths[$tableName]))
        {
            $basePath = $this->filePaths[$tableName];
            foreach ($results as &$row)
            {
                foreach ($this->fileFields as $field)
                {
                    if (isset($row[$field]) && !empty($row[$field]))
                    {
                        $subPath = isset($this->fileSubPaths[$field]) ? $this->fileSubPaths[$field] : '';
                        $row[$field] = 'https://iaf.kemlu.go.id' . str_replace('/var/www/html', '', $basePath . $subPath . $row[$field]);
                    }
                }
            }
        }
        return $results;
    }

    private function outputResponse($data, $error = null, $format = 'json')
    {
        if ($format === 'csv')
        {
            $this->outputCsv($data, $error);
        }
        else
        {
            $this->outputJson($data, $error);
        }
    }

    private function outputCsv($data, $error = null)
    {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="export.csv"');
        $output = fopen('php://output', 'w');

        if ($error)
        {
            fputcsv($output, [$error]);
        }
        else
        {
            if (!empty($data))
            {
                fputcsv($output, array_keys(reset($data))); // Output header row
                foreach ($data as $row)
                {
                    fputcsv($output, $row);
                }
            }
        }

        fclose($output);
    }

    private function outputJson($data, $error = null)
    {
        header('Content-Type: application/json; charset=utf-8');
        if ($error)
        {
            echo json_encode(['error' => $error], JSON_PRETTY_PRINT);
        }
        else
        {
            echo json_encode($data, JSON_PRETTY_PRINT);
        }
    }
}