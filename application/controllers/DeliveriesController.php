<?php 
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
class DeliveriesController extends CI_Controller 
{

	public function __construct() {
		parent::__construct();
	 
	}

	public function new() {
		$this->load->model('PriceModel');
		$data['page'] = "New Delivery";
		$data['suppliers'] = $this->db->get('supplier')->result();
		$data['products'] = json_encode($this->db->select('items.id as data, items.name as value, prices.capital')->join('prices', 'prices.item_id = items.id')->get('items')->result());
		 
 		$data['content'] = "deliveries/new";
		$this->load->view('master',$data);
		 
	}

	public function details($id) {
		$data['delivery'] = $this->db->select('delivery.*, supplier.name')
								->from('delivery')
								->join('supplier', 'supplier.id = delivery.supplier_id')
								->get()
								->row();
		 
		$data['deliveryDetails'] = $this->db->select("delivery_details.*, items.name as product_name, SUM(delivery_details.price * delivery_details.quantities) as subTotal")
								->join('items', 'items.id = delivery_details.item_id', 'BOTH')
								->where('delivery_details.delivery_id', $id)
								->group_by('delivery_details.id')
								->get('delivery_details')->result();
		$data['total'] = 0;
		$data['content'] = "deliveries/details";
		return $this->load->view('master',$data);
	}


	public function insert() {
	 
		$products = $this->input->post("product");
		$products_id = $this->input->post("product_id");
		$expiry_date = $this->input->post("expiry_date");
		$price = $this->input->post("price");
		$quantity = $this->input->post("quantity");
		$defectives = $this->input->post("defective");
		$remarks = $this->input->post("remarks");

		$data = array(
			'supplier_id' => $this->input->post('supplier_id'),
			'date_time' => $this->input->post('delivery_date'),
			'received_by' => $this->session->userdata('username')
			);

		$data = $this->security->xss_clean($data);
		$this->db->trans_begin();
		$this->db->insert('delivery',$data);
		$delivery_id = $this->db->insert_id();
		$orderDetails = array();

		foreach ($products as $key => $product) {
			if (!$products_id[$key])
				continue;
			
			$orderDetails[] = array(
				'item_id'	=> $products_id[$key],
				'quantities' => $quantity[$key],
				'delivery_id' => $delivery_id,
				'price'	=>	$price[$key],
				'expiry_date' => $expiry_date[$key],
				'defectives' => $defectives[$key],
				'remarks'	=> $remarks[$key]
			);
 			//Update Product Quantities
			$this->db->set('quantity', 'quantity+' . $quantity[$key], FALSE);
			$this->db->where('item_id', $products_id[$key]);
			$this->db->update('ordering_level'); 
		}
  
		$this->db->insert_batch('delivery_details', $orderDetails);
	 	
	 	if ( $this->db->trans_status() === FALSE ) {
			 
	        $this->db->trans_rollback();
	        $this->session->set_flashdata('error', 'Opps! something went wrong please try again');
			return redirect('new-delivery');
		} 

		$this->db->trans_commit();  
		$this->session->set_flashdata('success', 'Delivery saved successfully');
		return redirect('new-delivery'); 

	}

	public function index() {
	
		$deliveries = $this->db->select("delivery.*, supplier.name, SUM(delivery_details.quantities * delivery_details.price) as total, SUM(delivery_details.defectives) as defectives")
							->from('delivery') 
							->join('supplier', 'supplier.id = delivery.supplier_id', 'both')
							->join('delivery_details', 'delivery_details.delivery_id = delivery.id')
							->group_by('delivery.id')
							->get()->result();
 
		$data['deliveries'] = $deliveries;
 		$data['content'] = "deliveries/index";
		$this->load->view('master',$data);
		 
	}

	public function destroy($id) {
		$id = $this->security->xss_clean($id);
		$this->db->where('delivery_id', $id)->delete('delivery_details');
		$this->db->where('id', $id)->delete('delivery');
		$this->session->set_flashdata('success', "Delivery deleted successfully");
		return redirect(deliveries);
	}

}