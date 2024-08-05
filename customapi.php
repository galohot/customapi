<?php
defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Factory;
use Joomla\CMS\Response\JsonResponse;

class PlgSystemCustomapi extends CMSPlugin
{
    public function onAfterInitialise()
    {
        $app = Factory::getApplication();
        $input = $app->input;

        // Check if this is a request to the custom API endpoint
        if ($input->get('option') !== 'com_customapi') {
            return;
        }

        // Authenticate request
        $apiKey = $this->params->get('api_key');
        $requestKey = $input->get('api_key');
        if ($apiKey !== $requestKey) {
            $this->outputJson(null, JText::_('PLG_SYSTEM_CUSTOMAPI_INVALID_API_KEY'), true);
            $app->close();
        }

        // Get table name from parameters
        $tableName = $this->params->get('table_name');
        if (empty($tableName)) {
            $this->outputJson(null, 'Table name not specified', true);
            $app->close();
        }

        // Determine format
        $format = $input->getWord('format', 'json');

        // Get selected fields from query parameters
        $fields = $input->getString('fields', '*');

        // Fetch data from the specified table
        $db = Factory::getDbo();
        $query = $db->getQuery(true)
            ->select($fields)
            ->from($db->quoteName($tableName));
        $db->setQuery($query);
        $results = $db->loadAssocList();

        // Output data
        if ($format === 'csv') {
            $this->outputCsv($results);
        } else {
            $this->outputJson($results);
        }

        $app->close();
    }

    private function outputJson($data, $message = null, $error = false)
    {
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    private function outputCsv($data)
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment;filename=export.csv');
        $output = fopen('php://output', 'w');
        if (count($data) > 0) {
            fputcsv($output, array_keys($data[0]));
            foreach ($data as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
    }
}