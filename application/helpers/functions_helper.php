<?php 

	function dd($data) {
		echo "<pre>";
		print_r($data);
		echo "</pre>";
		die();
	}

	function currency() {
		return "₱";
	}

	function profile() {
		return explode(',', base64_decode(file_get_contents("./profile.txt")));
	}

	function serial() {
		$serial =  shell_exec('c:\Windows\System32\wbem\wmic.exe DISKDRIVE GET SerialNumber 2>&1');
		return trim(str_replace('SerialNumber','', $serial));
	}

	function noStocks() {
		$CI =& get_instance();
		$outOfStocks = $CI->db->select("items.id, items.name, ordering_level.quantity")
				->from("items")
				->join("ordering_level", "ordering_level.item_id = items.id", "left")
				->where('items.status', 1)
				->where('ordering_level.quantity <=', 0)
				->get();

		return $outOfStocks;
	}

	function is_admin() {
		$CI =& get_instance();
		return ($CI->session->userdata('account_type') === "Admin") ? 1 : 0;
	}

	function success($message) {
		$CI =& get_instance();
		$CI->session->set_flashdata('success', $message);
	}

	function license($table) {
		$CI =& get_instance();
		
	 

		$data['bronze'] = [
				'items' => 300,
				'users' => 5,
				'customers' => 500
			];

		$data['silver'] = [
				'items' => 5000,
				'users' => 5000,
				'customers' => 5000
			];

		$data['gold'] = [
				'items' => 5000000,
				'users' => 5000000,
				'customers' => 5000000
			];

		$license = $CI->config->item('license');
		$count = $CI->db->get($table)->num_rows();
		 
		if ($count > $data[$license][$table] ) {
			$CI->session->set_flashdata('errorMessage', "<div class='alert alert-danger'>Your ". $table ." reached the limit, please contact us to upgrade</div>");
			return redirect('/items');
		}
		 
	}