<?php
/**
 * Indonesia Shipping Carriers
 * @copyright   Copyright (c) 2015 Ansyori B.
 * @email		ansyori@gmail.com / ansyori@kemanaservices.com
 * @build_date  March 2015   
 * @license     http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

class Ansyori_Aongkir_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function setLog($message,$filename='aongkir.log')
	{
		Mage::log($message,null,$filename);
	}
	
	public function config($config)
	{
		return Mage::getStoreConfig('carriers/ongkir/'.$config);
	}
	
	
	public function getApiKey()
	{
		return $this->config('apikey');
	}
	
	public function getApiUrl()
	{
		return $this->config('apiurl');
	}
	
	public function getRates($origin,$dest,$weight,$kurir)
	{
		$weight = $weight * 1000;
		$post_fields = "origin=$origin&destination=$dest&weight=$weight&courier=$kurir";
		
		
		$post = $this->requestPost('/cost',$post_fields);
		
		$array_rates = array();
		
		if($post['rajaongkir']['status']['code'] != '200')
		{
			return false;
		};
		
		$name_kurir = strtoupper($post['rajaongkir']['results'][0]['code']);
		
		foreach($post['rajaongkir']['results'][0]['costs'] as $listrates)
		{
			$text = $name_kurir.' '.'('.$listrates['service'].') '.$listrates['description'];
			
			
			foreach($listrates['cost'] as $main_rates):
			$array_rates[] = array(
			
					'text'=> $text.' '.$main_rates['note'],
					'cost'=> $main_rates['value']
			);
			
			endforeach;
		}
		
		
		return $array_rates;
		
	}
	
	public function requestPost($method,$postfield)
	{
		
		$this->setLog('POST FIELDS : '.$postfield);
		$this->setLog('URL : '.$this->getApiUrl().$method);
		
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  CURLOPT_URL => $this->getApiUrl().$method,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "POST",
		  //CURLOPT_POSTFIELDS => "origin=501&destination=114&weight=1700&courier=jne",
		  CURLOPT_POSTFIELDS => $postfield,
		  CURLOPT_HTTPHEADER => array(
			"key: ".$this->getApiKey()
		  ),
		));
		
		$response = curl_exec($curl);
		/*echo '<pre>';
		print_r(json_decode($response,true));
		
		$err = curl_error($curl);*/
		$json = json_decode($response,true);
		$array = print_r($json,true);
		
		curl_close($curl);
		
		$this->setLog('API LOG REQ : '.$array);
		
		/* array */
		return $json;
		
	}
	
	
	public function request($method)
	{
		$curl = curl_init();

		curl_setopt_array($curl, array(
		  //CURLOPT_URL => "http://rajaongkir.com/api/starter/city",
		  CURLOPT_URL => $this->getApiUrl().$method,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_ENCODING => "",
		  CURLOPT_MAXREDIRS => 10,
		  CURLOPT_TIMEOUT => 30,
		  CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
		  CURLOPT_CUSTOMREQUEST => "GET",
		  CURLOPT_HTTPHEADER => array(
			"key: ".$this->getApiKey()
		  ),
		));
		
		$response = curl_exec($curl);
		/*echo '<pre>';
		print_r(json_decode($response,true));
		
		$err = curl_error($curl);*/
		$this->setLog('API LOG REQ : '.print_r(json_decode($response,true)));
		curl_close($curl);
		
		/* array */
		return json_decode($response,true);
		
	}
	
	public function getAllCity()
	{
		return $this->request('/city');
	}
	
	public function getAllProvince()
	{
		return $this->request('/province');
	}
	
	public function getCityProvince($provinceId)
	{
		return $this->request('/city?province='.$provinceId);
	}
	
	public function isCityExist($city_id)
	{
		$model = Mage::getModel('aongkir/area')->getCollection()
				->addFieldToFilter('city_id',$city_id)
				->getFirstItem();
		return $model->getId();
	}
	
	public function fetchSql($sql)
	{
		try{
			$resource = Mage::getSingleton('core/resource');
			$writeConnection = $resource->getConnection('core_read');
			return  $writeConnection->fetchAll($sql);

		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		}
	}
	
	public function sql($sql)
	{
		try{
			$resource = Mage::getSingleton('core/resource');
			$writeConnection = $resource->getConnection('core_write');
			return  $writeConnection->query($sql);

		}catch(Exception $xx)
		{
			$this->setLog('Erorr Sql : '.$xx->getMessage());
			return false;
		}
	}
	
	public function getPrefixTable()
	{
		return  Mage::getConfig()->getTablePrefix();
	}
	
	public function saveAreaToDb()
	{
		$data = $this->getAllProvince();
		
		$model = Mage::getModel('aongkir/area');
		
		$sql_delete_region = "
			DELETE FROM ".$this->getPrefixTable()."directory_country_region WHERE country_id = 'ID' 
		";
		
		
		$this->sql($sql_delete_region);
		
		foreach($data['rajaongkir']['results'] as $prov)
		{
			/*$data_to_save = array();
			$data_to_save = array(
			
					'city_id' => $api_data['city_id']
			);
			*/
			
			$code_prov = $prov['province_id'];
			$name_prov = $prov['province'];
			
			
			$sql_insert_province = "
			INSERT INTO  directory_country_region (country_id,code,default_name)
			VALUES ('ID','$code_prov','$name_prov')
			";
			
			$this->sql($sql_insert_province);
			
			$data_city = $this->getCityProvince($prov['province_id']);
			foreach($data_city['rajaongkir']['results'] as $api_data):
				try{
					$check_id = $this->isCityExist($api_data['city_id']);
					//$api_data['city_id'];
					if(!$check_id)
					{
						$model->setData($api_data)
						->save();
					}else
					{
						$model->load($check_id)
							  ->addData($api_data)
							  ->setId($check_id)
							  ->save();
					}
				}catch(Exception $xx)
				{
					$this->setLog('insert data area failed '.$xx->getMessage());
				}
			endforeach;
		}
		
		
	}
	
	public function getJsCity()
	{
		$model = Mage::getModel('aongkir/area');
		$provice = $model->getCollection()
					->distinct(true)
					->addFieldToSelect('province_id')
					->addFieldToSelect('province')
					
					->load()
					;
		
		$string = '';
		
		foreach($provice as $data)
		{
			$string .= "
			var provice_".$data->getProvinceId()." = [ ";
			
			
			$city = $model->getCollection()
					->distinct(true)
					->addFieldToFilter('province_id',$data->getProvinceId())
					->addFieldToSelect('city_id')
					->addFieldToSelect('city_name')
					->addFieldToSelect('type')
					
					
					->load()
					;
			foreach($city as $data_city)
			{
				$string .= "
			{ value: '".$data_city->getType().' '.$data_city->getCityName()."', data: '".$data_city->getCityId()."' },";
			}
			
			$string .="];
			
			";
			
		}
		
		return $string;
	}
	
}
	 