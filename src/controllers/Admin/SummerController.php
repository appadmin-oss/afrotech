<?php
namespace Admin;

class SummerController extends \Controller {
    public function index(): void {
        \Rbac::require('registrations.view');
        $filters = [
            'status' => (string)$this->input('status', 'all'),
            'track'  => (string)$this->input('track', ''),
            'q'      => (string)$this->input('q', ''),
        ];
        $this->view('admin/summer/index', [
            'title'   => 'Summer registrations · ' . AFT_NAME . ' Ops',
            'rows'    => \SummerRegistration::query($filters),
            'stats'   => \SummerRegistration::stats(),
            'filters' => $filters,
            'tracks'  => \Track::options(),
        ], 'admin');
    }

    public function show(string $id): void {
        \Rbac::require('registrations.view');
        $row = \SummerRegistration::find((int)$id);
        if (!$row) { $this->notFound(); return; }
        $this->view('admin/summer/show', [
            'title' => $row['reg_code'] . ' · ' . AFT_NAME . ' Ops',
            'r'     => $row,
        ], 'admin');
    }

    public function updateStatus(string $id): void {
        \Csrf::require();
        \Rbac::require('registrations.manage');
        \SummerRegistration::setStatus((int)$id, (string)$this->input('status', 'pending'), \Auth::id());
        $this->redirect('/admin/summer/' . (int)$id);
    }

    public function updatePayment(string $id): void {
        \Csrf::require();
        \Rbac::require('registrations.manage');
        \SummerRegistration::setPayment((int)$id, (string)$this->input('payment_status', 'unpaid'));
        $this->redirect('/admin/summer/' . (int)$id);
    }

    /** CSV export of the current filter set. */
    public function export(): void {
        \Rbac::require('registrations.manage');
        $rows = \SummerRegistration::query([
            'status' => (string)$this->input('status', 'all'),
            'track'  => (string)$this->input('track', ''),
            'q'      => (string)$this->input('q', ''),
        ]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="afrotech-summer-registrations-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Reg code','Student','Age','Guardian','Email','Phone','Track','Location','Experience','Status','Payment','Registered']);
        foreach ($rows as $r) {
            fputcsv($out, [
                $r['reg_code'], $r['student_name'], $r['student_age'], $r['guardian_name'],
                $r['email'], $r['phone'], $r['track_name'], $r['location_pref'],
                $r['experience'], $r['status'], $r['payment_status'], $r['created_at'],
            ]);
        }
        fclose($out);
        exit;
    }
}
