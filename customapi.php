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

    public function onAfterInitialise()
    {
        $input = Factory::getApplication()->input;
        $option = $input->get('option', '', 'cmd');
        $apiKey = $this->params->get('api_key');

        if ($option === 'com_customapi')
        {
            $providedApiKey = $input->get('api_key', '', 'string');
            $tableName = $input->get('table', '', 'string'); // Get the table name from URL parameters
            $fields = $input->get('fields', '*', 'string'); // Get the fields from URL parameters, default to '*'
            $format = $input->get('format', 'json', 'string'); // Get the response format from URL parameters (default to json)

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
                    // Fetch all columns if fields is '*'
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

                $this->outputResponse($results, null, $format);
            }
            catch (Exception $e)
            {
                $this->outputResponse(null, $e->getMessage(), $format);
            }

            Factory::getApplication()->close();
        }
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
        if ($error)
        {
            echo json_encode(['error' => $error]);
        }
        else
        {
            echo json_encode($data);
        }
    }
}