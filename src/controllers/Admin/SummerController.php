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

        // Replay the answers against the exact form version that collected
        // them, so a question renamed since then still reads correctly here.
        $version = (int)($row['form_version'] ?? 1);
        $def = \FormDef::findVersion(\FormDef::SUMMER, $version) ?? \FormDef::live();
        $answers = \SummerRegistration::answers($row);

        $this->view('admin/summer/show', [
            'title'       => $row['reg_code'] . ' · ' . AFT_NAME . ' Ops',
            'r'           => $row,
            'formVersion' => $version,
            'answerRows'  => $answers
                ? \FormEngine::presentAnswers(\FormEngine::resolve($def['fields'] ?? []), $answers, true)
                : [],
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

        $status = (string)$this->input('payment_status', 'unpaid');
        $reg    = \SummerRegistration::find((int)$id);

        // Marking a registration paid used to update this row and nothing else:
        // the payment stayed pending in the ledger and the family never got a
        // receipt. If there is a payment waiting on this registration, confirm
        // it properly instead — same path, same receipt, same audit trail.
        if ($status === 'paid' && $reg && \Rbac::can('payments.confirm')) {
            $pay = \Payment::forRegistration((int)$reg['id']);
            if ($pay && $pay['status'] === 'pending') {
                $res = (new \PaymentController())->confirmManually(
                    $pay, $reg, \Auth::id(), 'Marked paid from the registration record'
                );
                flash_set($res['ok'] ? 'summer_msg' : 'summer_err', $res['message']);
                $this->redirect('/admin/summer/' . (int)$id);
                return;
            }
        }

        \SummerRegistration::setPayment((int)$id, $status);
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
        // Builder answers become their own columns, in form order, using the
        // live definition's labels. Fields flagged sensitive are omitted —
        // that flag exists precisely to keep them out of spreadsheets.
        $live     = \FormDef::live();
        $resolved = \FormEngine::resolve($live['fields'] ?? []);
        $core     = array_keys(\FormEngine::MAPPABLE);
        $extra    = [];
        foreach ($resolved as $f) {
            if (in_array($f['type'], \FormEngine::LAYOUT_TYPES, true)) continue;
            if (!empty($f['sensitive'])) continue;
            if (!empty($f['map']) && in_array($f['map'], $core, true)) continue;
            $extra[$f['key']] = ['label' => $f['label'], 'labels' => \FormEngine::optionLabels($f)];
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="afrotech-summer-registrations-' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, array_merge(
            ['Reg code','Student','Age','Guardian','Email','Phone','Track','Location','Experience','Fee','Add-ons','Status','Payment','Form version','Registered'],
            array_map(fn($m) => $m['label'], $extra)
        ));
        foreach ($rows as $r) {
            $answers = \SummerRegistration::answers($r);
            $line = [
                $r['reg_code'], $r['student_name'], $r['student_age'], $r['guardian_name'],
                $r['email'], $r['phone'], $r['track_name'], $r['location_pref'],
                $r['experience'], $r['fee_naira'], $r['addons_naira'] ?? 0,
                $r['status'], $r['payment_status'], $r['form_version'] ?? 1, $r['created_at'],
            ];
            foreach ($extra as $key => $meta) {
                $v = $answers[$key] ?? '';
                if (is_array($v)) $v = implode('; ', array_map(fn($x) => $meta['labels'][(string)$x] ?? (string)$x, $v));
                else $v = $meta['labels'][(string)$v] ?? (string)$v;
                $line[] = $v;
            }
            fputcsv($out, $line);
        }
        fclose($out);
        exit;
    }
}
