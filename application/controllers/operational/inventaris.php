<?php

if (!defined('BASEPATH'))
    exit('No direct script access allowed');
date_default_timezone_set('Asia/Jakarta');

class Inventaris extends CI_Controller {

    function __construct() {
        parent::__construct();
        session_start();
        $this->load->model('home_m');
        $this->load->model('admin/konfigurasi_menu_status_user_m');
//        $this->load->model('zsessions_m');
        $this->load->model('global_m');
        $this->load->model('operational/inventaris_mdl', 'inventaris');
        $this->load->model('datatables_custom');

//        $sess = $this->zsessions_m->get_sess_data();
//        echo '<pre>';print_r($sess);  
//        if (sizeof($sess) > 0) {
//            $this->userid = $sess->id_user;
//            $this->username = $sess->username;
//            $this->role = $sess->usergroup_desc;
//        } else {
//            redirect('main/logout');
//        }
    }

    public function index() {
        if ($this->auth->is_logged_in() == false) {
            $this->login();
        } else {
            $data['multilevel'] = $this->user_m->get_data(0, $this->session->userdata('usergroup'));

            $this->template->set('title', 'Home');
            $this->template->load('template/template1', 'global/index', $data);
        }
    }

    function home() {
        $menuId = $this->home_m->get_menu_id('operational/inventaris/home');
        $data['menu_id'] = $menuId[0]->menu_id;
        $data['menu_parent'] = $menuId[0]->parent;
        $data['menu_nama'] = $menuId[0]->menu_nama;
        $data['menu_header'] = $menuId[0]->menu_header;
        $this->auth->restrict($data['menu_id']);
        $this->auth->cek_menu($data['menu_id']);
        $data['group_user'] = $this->konfigurasi_menu_status_user_m->get_status_user();
        //$data['level_user'] = $this->sec_user_m->get_level_user();
        if (isset($_POST["idTmpAksiBtn"])) {
            $act = $_POST["idTmpAksiBtn"];
            if ($act == 1) {
                $this->simpan();
            } elseif ($act == 2) {
                $this->ubah();
            } elseif ($act == '3') {
                $this->hapus();
            } else {
                $data['multilevel'] = $this->user_m->get_data(0, $this->session->userdata('usergroup'));
                $data['menu_all'] = $this->user_m->get_menu_all(0);
                $data['karyawan'] = $this->global_m->tampil_id_desk('master_karyawan', 'id_kyw', 'nama_kyw', 'id_kyw');
                $data['goluser'] = $this->global_m->tampil_id_desk('sec_gol_user', 'goluser_id', 'goluser_desc', 'goluser_id');

                $data['statususer'] = $this->global_m->tampil_id_desk('sec_status_user', 'statususer_id', 'statususer_desc', 'statususer_id');
                $this->template->set('title', 'Term Of Payment');
                $this->template->load('template/template_dataTable', 'operational/inventaris/inventaris_v', $data);
            }
        } else {
            $data['multilevel'] = $this->user_m->get_data(0, $this->session->userdata('usergroup'));
            $data['menu_all'] = $this->user_m->get_menu_all(0);
            $data['karyawan'] = $this->global_m->tampil_id_desk('master_karyawan', 'id_kyw', 'nama_kyw', 'id_kyw');
            $data['goluser'] = $this->global_m->tampil_id_desk('sec_gol_user', 'goluser_id', 'goluser_desc', 'goluser_id');
            $data['statususer'] = $this->global_m->tampil_id_desk('sec_status_user', 'statususer_id', 'statususer_desc', 'statususer_id');

            $this->template->set('title', 'Term Of Payment');
            $this->template->load('template/template_dataTable', 'operational/inventaris/inventaris_v', $data);
        }
    }

    public function ajax_GridMutation() {
        $lokasi = $this->inventaris->getLokasi($this->session->userdata('id_user'));
        $kodebranch = $this->inventaris->getCodeBranch($lokasi->BranchID);
        if ((int) $kodebranch == 00000) { //pusat
            $kodecabang = $this->inventaris->getCodeDivisi($lokasi->DivisionID);
        } else {
            $kodecabang = $kodebranch;
        }
        $div = $this->session->userdata('DivisionID');
        $branch = $this->session->userdata('BranchID');
        $usergroup = $this->session->userdata('groupid');
        $iwhere1 = array($this->input->post('sSearch') => $_POST['search']['value']);
        $iwhere2 = array();
        $iwhere3 = array();
        $param_in = 'Status';
        $iOrWhere = array('Status'=>4,'kode_cab'=>$kodecabang);
        if ($branch == 1) { //JIKA PUSAT : semua ppi bisa lihat data kecuali ppi dengan usergroup support
            if (($div == '8' && $usergroup <> '3') || $div == '20' || $usergroup == '1') {
//                $iwhere1 = array($this->input->post('sSearch') => $_POST['search']['value']);
            } else {
                $iwhere2 = array('DivisionID' => $div);
            }
        } else { //JIKA CABANG : cabang hanya bisa melihat cabang dan divisinya saja
            $iwhere3 = array('BranchID' => $branch);
        }

        if ($_POST['search']['value'] != "") {
            $where_in = array(9, 4);
        } else {
//            $where_in = array(1, 3);
            $where_in = array(9, 4, 2);
        }
        $iwhere = array_merge($iwhere1, $iwhere2, $iwhere3);
        $icolumn = array('ZoneName', 'BranchDivName', 'FAID', 'ItemName', 'QTY', 'ClassCode', 'ReqTypeID', 'BranchName', 'BranchCode', 'DivisionName', 'Raw_ID', 'Period', 'PriceVendor', 'SetDatePayment', 'Status', 'Is_trash', 'kode_cab');
        $iorder = array('Raw_ID' => 'asc');
        $list = $this->datatables_custom->get_datatables('vw_opr_inventaris', $icolumn, $iorder, $iwhere, $where_in, $param_in, array(), null,$iOrWhere);

        $data = array();
        $no = $_POST['start'];
        foreach ($list as $idatatables) {
            $irow = '';
            if (!empty($idatatables->FAID)) {
                $irow = '<a href=" base_url()uploads/qr_code/ trim($idatatables->FAID)" download=" trim($idatatables->FAID)"><img src="base_url(); ?>uploads/qr_code/ trim($idatatables->FAID)" style="width: 30px; height:30px"></a>';
            }

            $no++;
            $row = array();
            $row[] = $no;

            $row[] = $idatatables->ZoneName;
            $row[] = $idatatables->BranchDivName;
            $row[] = $idatatables->FAID;
            $row[] = $idatatables->ItemName;
            $row[] = $idatatables->QTY;
            $row[] = $irow;

            $data[] = $row;
        }
//        print_r($data);
//        die();
        $output = array(
            "draw" => $_POST['draw'],
            "recordsTotal" => $this->datatables_custom->count_all(),
            "recordsFiltered" => $this->datatables_custom->count_filtered(),
            "data" => $data,
        );
        //output to json format
        echo json_encode($output);
    }

}

/* End of file sec_user.php */
/* Location: ./application/controllers/sec_user.php */