<?php
/**
 * Submit or update data to a REST service
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.rest
 * @copyright   Copyright (C) 2005-2016  Media A-Team, Inc. - All rights reserved.
 * @license     GNU/GPL http://www.gnu.org/copyleft/gpl.html
 */

// No direct access
defined('_JEXEC') or die('Restricted access');

use Joomla\Utilities\ArrayHelper;

// Require the abstract plugin class
require_once COM_FABRIK_FRONTEND . '/models/plugin-form.php';

/**
 * Submit or update data to a REST service
 *
 * @package     Joomla.Plugin
 * @subpackage  Fabrik.form.rest
 * @since       3.0
 */
class PlgFabrik_FormRest extends PlgFabrik_Form
{
    protected $api_url;
    protected $sent = false;
    protected $method;

    protected function getInfoData() {
        $params = $this->getParams();
        $formModel = $this->getModel();
        $listName = $formModel->getTableName();
        $worker = FabrikWorker::getPluginManager();

        $this->api_url = $params->get('api_url', '') . '/index.php?option=com_fabrik&format=raw&task=plugin.pluginAjax&plugin=fabrik_api&method=apiCalled&g=list';
        if (!$this->api_url) {
            return false;
        }

        $info = array();
        $authentication = new stdClass();
        $authentication->api_key = $params->get('api_key', '');
        $authentication->api_secret = $params->get('api_secret', '');
        $info['authentication'] = json_encode($authentication);

        $options = new stdClass();
        $options->list_id = $params->get('rest_list_id');

        $auxiliarElementId = $params->get('rest_auxiliar_id');
        $auxiliarElement = $worker->getElementPlugin($auxiliarElementId)->element->name;
        $idAuxiliar = $formModel->formData[$auxiliarElement];
        if (!$idAuxiliar) {
            $this->method = 'POST';
        }
        else {
            $auxiliar = json_decode($idAuxiliar);
            $auxiliar = (array) $auxiliar;
            $idAuxiliar = $auxiliar[$authentication->api_key];
            $options->row_id = (string) $idAuxiliar;
            $this->method = 'PUT';
        }



        $fields = json_decode($params->get('rest_elements_list'));
        $keys = $fields->rest_element_key;
        $values = $fields->rest_element_value;
        $defaults = $fields->rest_element_default;
        $row = new stdClass();
        $i = 0;
        foreach ($keys as $key) {
            $search = str_replace("$listName.", '', $values[$i]);
            $row->$key = $formModel->formData[$search] ? $formModel->formData[$search] : $defaults[$i];
            $i++;
        }

        if ($this->method === 'PUT') {
            $options->row_data = $row;
        }
        else {
            $options->row_data = array($row);
        }
        $info['options'] = json_encode($options);

        return $info;
    }

    protected function sendData($info) {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_URL, $this->api_url);
        if ($this->method === 'PUT') {
            curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($info));
        }
        else {
            curl_setopt($curl, CURLOPT_POST, 1);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $info);
        }
        $response = curl_exec($curl);
        curl_close($curl);

        $response = json_decode($response);

        if ($response->data->row_id[0]) {
            $this->setIdAuxiliar($response->data->row_id[0]);
        }
    }

    protected function setIdAuxiliar($id) {
        $params = $this->getParams();
        $formModel = $this->getModel();
        $listName = $formModel->getTableName();
        $worker = FabrikWorker::getPluginManager();
        $rowId = $formModel->formData["{$listName}___id"];

        $api_key = $params->get('api_key', '');

        $auxiliarElementId = $params->get('rest_auxiliar_id');
        $auxiliarElement = $worker->getElementPlugin($auxiliarElementId)->element->name;
        $idAuxiliar = $formModel->formData[$auxiliarElement];
        if (!$idAuxiliar) {
            $auxiliar = new stdClass();
            $auxiliar->$api_key = $id;
        }
        else {
            $auxiliar = json_decode($idAuxiliar);
            $auxiliar->$api_key = $id;
        }

        $update = new stdClass();
        $update->id = $rowId;
        $update->$auxiliarElement = json_encode($auxiliar);
        return JFactory::getDbo()->updateObject($listName, $update, 'id');
    }

	public function onAfterProcess()
	{
	    if (!$this->sent) {
            $this->sent = true;
            $info = $this->getInfoData();

            if ($info) {
                $this->sendData($info);
            }
        }
	}
}
