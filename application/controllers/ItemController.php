<?php
if ( ! defined('BASEPATH')) exit('No direct script access allowed');
require_once(APPPATH."controllers/AppController.php");
class ItemController extends AppController { 
	
	public function __construct() { 

		parent::__construct();
 		$this->load->model('categories_model');
		$this->load->model('PriceModel');
		$this->load->model('OrderingLevelModel');
		$this->load->model('ItemModel');
		$this->load->model('HistoryModel');
		$this->load->config('license');
		$this->licenseControl();

		if (!$this->session->userdata('log_in')) {
			$this->session->set_flashdata('errorMessage','<div class="alert alert-danger">Login Is Required</div>');
			redirect(base_url('login'));
		}
	
	}

	public function find() {
		$barcode = $this->input->post('code');
		$item = $this->db->where('barcode', $barcode)->get('items')->row();
		if ($item) {
			$quantity = (int)$this->OrderingLevelModel->getQuantity($item->id)->quantity;
			$price = $this->db->where('item_id', $item->id)->get('prices')->row()->price;
			echo json_encode([
					'name' => $item->name,
					'price' => '₱' . $price,
					'quantity' => $quantity,
					'id' => $item->id
				]) ;
		} 
		return;
	}

   public function do_upload($file)
    { 
        $config['upload_path']          = './uploads/';
        $config['allowed_types']        = 'gif|jpg|png|jpeg';
        $config['max_size']             = 2000;
        $config['max_width']            = 2000;
        $config['max_height']           = 2000;
        $config['encrypt_name'] 		= TRUE;
        $this->load->library('upload', $config);
        $this->upload->initialize($config);

        if ( ! $this->upload->do_upload($file))
        {
        	return $error = array('error' => $this->upload->display_errors());
        }
         
        return $data = array('upload_data' => $this->upload->data());

    }

	public function items () {
		 
		$data['total'] = number_format($this->ItemModel->total()->total,2);
		$data['suppliers'] = $this->db->get('supplier')->result();
		$data['page'] = 'inventory';
		$data['price'] = $this->PriceModel;
		$data['categoryModel'] = $this->categories_model;
		$data['categories'] = $this->categories_model->getCategories();
		$data['orderingLevel'] = $this->OrderingLevelModel;
		$data['content'] = "items/index";
		$this->load->view('master', $data);

	}

	public function dataTable() {
		$start = $this->input->post('start');
		$limit = $this->input->post('length');
		$search = $this->input->post('search[value]'); 
		$items = $this->dataFilter($search, $start, $limit);
		$filterCategory = $this->input->post('columns[2][search][value]');
		$filterSupplier = $this->input->post('columns[7][search][value]');
		$sortPrice = $this->input->post('columns[4][search][value]');
		$sortStocks = $this->input->post('columns[5][search][value]');
		$itemCount = $this->db->get('items')->num_rows();

		if ($filterCategory) {
			$sortPrice = NULL;
			$sortStocks = NULL;
			$this->db->select('items.*,categories.id as cat_id');
			$this->db->from('items');
			$this->db->join('categories', 'categories.id = items.category_id', 'left');
			$this->db->where('categories.name', $filterCategory);
			if ($filterSupplier) {
				$this->db->join('supplier', 'supplier.id = items.supplier_id', 'left');
				$this->db->where('supplier.name', $filterSupplier);
			}
			$items = $this->db->get()->result();
		}

		if ($filterSupplier) {
			$sortPrice = NULL;
			$sortStocks = NULL;
			$this->db->select('items.*,supplier.id as cat_id');
			$this->db->from('items');
			$this->db->join('supplier', 'supplier.id = items.supplier_id', 'left');
			$this->db->where('supplier.name', $filterSupplier);
			if ($filterCategory) {
				$this->db->join('categories', 'categories.id = items.category_id', 'left');
				$this->db->where('categories.name', $filterCategory);
			}
			$items = $this->db->get()->result();
		}

		if ($sortPrice) {
			$sortStocks = NULL;
			$items = $this->db->select('items.*')
					->from('items')
					->join('prices', 'prices.item_id = items.id')
					->order_by('prices.price',$sortPrice)
					->get()
					->result();
		}

		if ($sortStocks) {
			$sortPrice = NULL;
			$items = $this->db->select('items.*')
					->from('items')
					->join('ordering_level', 'ordering_level.item_id = items.id')
					->order_by('ordering_level.quantity',$sortStocks)
					->get()
					->result();
		}

		$datasets = array_map(function($item) {
			$itemPrice = $this->PriceModel->getPrice($item->id);
			$itemCapital = $this->PriceModel->getCapital($item->id);
			$stocksRemaining = $this->OrderingLevelModel->getQuantity($item->id)->quantity ?? 0;
			$itemSupplier = $this->db->where('id', $item->supplier_id)->get('supplier')->row()->name ?? 'unset';
			$deleteAction = "";
			if ($this->session->userdata('account_type') == "Admin") {

				$deleteAction = '
						<li>
                        	<a href="'.base_url("items/edit/$item->id").'"><i class="fa fa-edit"></i> Edit</a> 
                        </li>
						<li>
                        	<a onclick="(this).nextSibling.submit()" class="delete-item" href="#">
					            <i class="fa fa-trash"></i>
					        Delete</a>
                        	'.form_open("items/delete").'
                        		<input type="hidden" name="id" value="'.$item->id.'">

                        	'.form_close().'
                        	</form> 
					    </li>';
			}

			$actions = '<div class="dropdown">
                    <a href="#" data-toggle="dropdown" class="dropdown-toggle btn btn-primary btn-sm">Actions <b class="caret"></b></a>
                    <ul class="dropdown-menu">
                    	<li>
                            <a href="' . base_url("items/stock-in/$item->id") .'">
                                <i class="fa fa-plus"></i> Stock In</a>
                        </li>
                        
                        '.$deleteAction.'
                    </ul>
                </div>';

			return [
				$this->disPlayItemImage($item->image),
				$item->name,
				$this->categories_model->getName($item->category_id),
				'₱' . number_format($itemCapital,2),
				'₱' . number_format($itemPrice,2),
				$stocksRemaining . ' pcs',
				currency() . number_format($itemPrice * $stocksRemaining,2),
				$itemSupplier,
				$actions
			];

		}, $items);

		echo json_encode([
			'draw' => $this->input->post('draw'),
			'recordsTotal' => count($datasets),
			'recordsFiltered' => $itemCount,
			'data' => $datasets
		]);
	}

	public function disPlayItemImage($image) {
		if ($image && file_exists('./uploads/' . $image)) {
			return "<div class='product-thumbnail'><img src='".base_url('uploads/' . $image)."' data-preview-image='".base_url('uploads/' . $image)."'> </div>";
		}
		return '<div class="product-thumbnail"><span style="color: rgba(0,0,0,0.4);font-size: 11px;">No Image</span></div>';
	}

	public function outOfStocks() {
 		$data['content'] = "items/stockout";
 		$data['items'] = noStocks()->result();
		$this->load->view('master', $data);
	}

	public function data() {
		$orderingLevel = $this->OrderingLevelModel;
		$price = $this->PriceModel;
		$start = $this->input->post('start');
		$limit = $this->input->post('length');
		$search = $this->input->post('search[value]'); 
		$items = $this->dataFilter($search, $start, $limit);
		$itemCount = $this->db->get('items')->num_rows();

		$datasets = array_map(function($item) use ($orderingLevel){
			$quantity = (int)$orderingLevel->getQuantity($item->id)->quantity;
			$price = $this->db->where('item_id', $item->id)->get('prices')->row()->price;
			
			return [
				$item->id,
				ucwords($item->name),
				ucfirst($item->description),
				$quantity,
				'₱'. number_format($price,2)
			];
		}, $items);

		$count = count($datasets);

		echo json_encode([
				'draw' => $this->input->post('draw'),
				'recordsTotal' => $count,
				'recordsFiltered' => $itemCount,
				'data' => $datasets
			]);
	}

	public function dataFilter($search, $start, $limit) {
		$this->db->limit($limit, $start);
		if ($search != "") {
			return $this->db->where('status', 1)
						->like('name',$search, 'BOTH')
						->get('items')
		 
						->result();
		} 
		
		return $this->db->where('status', 1)
						->get('items')
						->result();
	}

	public function new() {
		$this->userAccess('new');
		$this->load->model('categories_model'); 
		$data['category'] = $this->db->where('active',1)->get('categories')->result();
		$data['suppliers'] = $this->db->get('supplier')->result();
		$data['page'] = 'new_item';
		$data['content'] = "items/new";  
		$this->load->view('master', $data);
	}

	public function generateBarcode() { 
		
		$code = substr(uniqid(), 0, 7);
		$codeExist = $this->db->where('barcode', $code)->get('items')->row();
		while ($codeExist) {
			$code = substr(uniqid(), 0, 7);
		}
		return $code;
	}

	public function insert() {
		license('items');
		$this->demoRestriction();
		$name = $this->input->post('name');
		$category = $this->input->post('category');
		$description = $this->input->post('description');
		$supplier_id = $this->input->post('supplier');
		$barcode = $this->input->post('barcode');
		$price = $this->input->post('price'); 
		$capital = $this->input->post('capital');
		$productImage = $_FILES['productImage'];
	 
		$this->form_validation->set_rules('name', 'Item Name', 'required|max_length[100]|trim|strip_tags');
		$this->form_validation->set_rules('category', 'Category', 'required|trim');
		$this->form_validation->set_rules('description', 'Description', 'required|max_length[150]|trim|strip_tags');
		$this->form_validation->set_rules('barcode', 'Barcode', 'required|is_unique[items.barcode]|trim|strip_tags');
		$this->form_validation->set_rules('supplier', 'Supplier', 'required|trim|strip_tags');
		$this->form_validation->set_rules('price', 'Price', 'required|max_length[500000]|trim|strip_tags');   
 
		if ( $this->form_validation->run() == FALSE ) {
			$this->session->set_flashdata('errorMessage', 
					'<div class="alert alert-danger">'.validation_errors().'</div>'); 
			return redirect(base_url('items/new'));
		}

		$data = array(
				'name' => $name,
				'category_id' => $category,
				'description' => $description, 
				'supplier_id' => $supplier_id,
				'status' => 1,
				'barcode' => $barcode 
			);
		
		if ($productImage) {
			$upload = $this->do_upload('productImage');
			if (array_key_exists('upload_data', $upload)) 
				$data['image'] = $upload['upload_data']['file_name'];
		}
		
		$data = $this->security->xss_clean($data);
		$this->db->insert('items', $data);
		$item_id = $this->db->insert_id();
		$this->HistoryModel->insert('Register new item: ' . $name);
		$this->PriceModel->insert($price,$capital, $item_id);
		$this->OrderingLevelModel->insert($item_id);
		$this->session->set_flashdata('successMessage', '<div class="alert alert-success">New Item Has Been Added</div>'); 
		return redirect(base_url('items'));


	}

	public function delete(){
		$id = $this->input->post('id');
		$this->demoRestriction();
		$this->load->model('ItemModel');
		$this->load->model('HistoryModel');
		$item = $this->ItemModel->item_info($id);
 
		if ($this->ItemModel->deleteItem($id) != false) {

			$this->session->set_flashdata('successMessage', '<div class="alert alert-success">Item Deleted Successfully</div>');
			$this->HistoryModel->insert('Delete Item: ' . $item->name);
			return redirect(base_url('items'));
		}
		
		$this->session->set_flashdata('errorMessage', '<div class="alert alert-danger">Opps Something Went Wrong</div>');
		return redirect(base_url('items'));
		
	}

	public function demoRestriction() {

		if (SITE_LIVE) {

			$this->session->set_flashdata('errorMessage', '<div class="alert alert-danger">You cannot Add, Modify or Delete data in Demo Version.</div>');
			return redirect(base_url('items'));
		}
	}

	public function stock_in($id) {

		$id = $this->security->xss_clean($id);
		$this->load->model('ItemModel');
		$this->load->model('PriceModel');
		$this->load->model('OrderingLevelModel');
		$this->load->model('categories_model');
		$data['item_info'] = $this->ItemModel->item_info(urldecode($id));
		$data['item_id'] = $id;
		$data['price'] = $this->PriceModel;
		$data['orderingLevel'] = $this->OrderingLevelModel;
		$data['categoryModel'] = $this->categories_model;
		$data['content'] = "items/stockin";
		$this->load->view('master', $data);
	}

	public function add_stocks() {
	
		$itemID = $this->input->post('item_id');
		$itemName = $this->input->post('item_name');
		$stocks = $this->input->post('stocks');

		if (SITE_LIVE) {

			$this->form_validation->set_rules('stocks','Stocks','required|integer|max_length[500]');
			if($this->form_validation->run() === FALSE) {
				$this->session->set_flashdata('errorMessage','<div class="alert alert-danger">' .validation_errors() . '</div>');
				return redirect(base_url("items/stock-in/$itemID"));
			}
		}

		$this->load->model('HistoryModel'); 
		$this->load->model('OrderingLevelModel');

		$update = $this->OrderingLevelModel->addStocks($itemID,$stocks);
		$this->HistoryModel->insert('Stock In: ' . $stocks . ' - ' . $itemName);
		if ($update) {
			$this->session->set_flashdata('successMessage', '<div class="alert alert-info">Stocks Added</div> ');
			return redirect(base_url('items'));
		}

		$this->session->set_flashdata('errorMessage', '<div class="alert alert-danger">Opps Something Went Wrong Please Try Again</div> ');
		return redirect(base_url('items'));
		
		 
	}

	public function edit($id) {
		$this->userAccess('edit');
		$id = $this->security->xss_clean($id);
		$data['item'] = $this->db->where('id', $id)->get('items')->row();
		$data['price'] = $this->PriceModel;
		$data['categories'] = $this->db->where('active',1)->get('categories')->result();
		$data['suppliers'] = $this->db->get('supplier')->result();
		$data['stocks'] = $this->db->where('item_id', $id)->get('ordering_level')->row();
		$data['content'] = "items/edit";
		$this->load->view('master', $data);
	}

	public function update() {
		$this->demoRestriction();
		//validation Form
		$this->updateFormValidation();
		$this->load->model('HistoryModel');
 		$updated_name = strip_tags($this->input->post('name'));
		$updated_category = strip_tags($this->input->post('category'));
		$updated_desc = strip_tags(strtolower($this->input->post('description')));
		$updated_price = strip_tags($this->input->post('price')); 
		$capital = strip_tags($this->input->post('capital'));
		$id = strip_tags($this->input->post('id'));

		$stocks = $this->input->post('stocks');
		$item = $this->db->where('id', $id)->get('items')->row();
		$currentPrice = $this->PriceModel->getPrice($id);
		$reorder = $this->input->post('reorder');
		$productImage = $_FILES['productImage'];
		$supplier_id = $this->input->post('supplier');

		if ($productImage['name']) {
			$fileName = $this->db->where('id', $id)->get('items')->row()->image;
			$currentImagePath = './uploads/' . $fileName;
			//Delete Current Image
			if ($fileName) 
				unlink($currentImagePath);
			//Then Upload and save the image filename to database
			$upload = $this->do_upload('productImage');
			 
		}

		$this->db->where('item_id', $id)->update('ordering_level', ['quantity' => $stocks]);
		$price_id = $this->PriceModel->update($updated_price,$capital, $id);
		$update = $this->ItemModel->update_item($id,$updated_name,$updated_category,$updated_desc,$price_id, $upload['upload_data']['file_name'], $supplier_id, $this->input->post('barcode'));

		if ($update) {
			
			$this->session->set_flashdata('successMessage', '<div class="alert alert-success">Item Updated</div>');
			if ($item->name != $updated_name)
				$this->HistoryModel->insert('Change Item Name: ' . $item->name . ' to ' . $updated_name);
			 
			if ($item->description != $updated_desc)
				$this->HistoryModel->insert('Change '.$item->name.' Description: ' . $item->description . ' to ' . $updated_desc);
			if ($currentPrice != $updated_price) 
					$this->HistoryModel->insert('Change '.$item->name.' Price: ' . $currentPrice . ' to ' . $updated_price);
				if ($item->category_id != $updated_category) 
					$this->HistoryModel->insert('Change '.$item->name.' Category: ' . $this->categories_model->getName($item->category_id) . ' to ' . $this->categories_model->getName($updated_category));
			return redirect(base_url('items'));
		}
	 		
	}

	public function updateFormValidation() {
		
		$this->form_validation->set_rules('name', 'Item Name', 'required|max_length[100]');
		$this->form_validation->set_rules('category', 'Category', 'required|max_length[150]');
		$this->form_validation->set_rules('description', 'Description', 'required|max_length[150]');
		$this->form_validation->set_rules('price', 'Price', 'required|max_length[500000]'); 
		$this->form_validation->set_rules('id', 'required'); 
 
		if ($this->form_validation->run() == FALSE) {
			$this->session->set_flashdata('errorMessage', '<div class="alert alert-danger">Opss Something Went Wrong Updating The Item. Please Try Again.</div>');
				return redirect(base_url('items'));
		} 
	}

}