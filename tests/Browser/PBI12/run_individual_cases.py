"""Run each documented automated TC with an exact PHPUnit method/dataset filter.

Writes discovery, commands, JUnit and console evidence only under PBI11/PBI12/.runtime.
Does not change test assertions or the TC document.
"""
import json
from pathlib import Path
import re
import shlex
import subprocess
import xml.etree.ElementTree as ET

ROOT = Path(__file__).resolve().parents[3]
DOC = ROOT / 'docs/test-cases-pbi-11-12.md'
OUT = ROOT / 'tests/Browser/PBI12/.runtime/individual'


def cases_from_document():
    section = DOC.read_text().split('## TC dan hasil aktual', 1)[1].split('## Ringkasan', 1)[0]
    rows = {}
    for line in section.splitlines():
        if line.startswith(('| TC-11-', '| TC-AUTH-')):
            cols = [value.strip() for value in line.strip('|').split('|')]
            ident = cols[0].split(' ')[0]
            assert ident not in rows, ident
            rows[ident] = dict(zip(('id', 'trace', 'scenario', 'type', 'prerequisites',
                                    'input', 'steps', 'expected', 'previous_actual',
                                    'previous_status', 'previous_deprecation'), cols))
    return rows


def manifest():
    rows = cases_from_document()
    result = []
    for folder, limit in (('PBI11', 8), ('PBI12', 12)):
        for number in range(1, limit + 1):
            paths = list((ROOT / 'tests/Browser' / folder).glob(f'TC{number:02}*Test.php'))
            assert len(paths) == 1, paths
            path = paths[0]
            methods = re.findall(r'public function (test_\w+)\(', path.read_text())
            assert len(methods) == 1, methods
            ident = f'TC-11-{number:02}' if folder == 'PBI11' else f'TC-AUTH-{number:02}'
            result.append((ident, path, methods[0], None))
    # Explicit ID mapping; provider ordering and test counts do not define TC identity.
    mapping = {
        'TC-AUTH-13.1': ('test_single_empty_field_is_rejected', 'email kosong'),
        'TC-AUTH-13.2': ('test_single_empty_field_is_rejected', 'password kosong'),
        'TC-AUTH-13.3': ('test_invalid_credentials_show_exactly_the_same_generic_message', None),
        'TC-AUTH-13.4': ('test_allowed_route_and_logout_for_each_role', 'masyarakat'),
        'TC-AUTH-13.5': ('test_allowed_route_and_logout_for_each_role', 'pemerintah'),
        'TC-AUTH-13.6': ('test_allowed_route_and_logout_for_each_role', 'admin'),
        'TC-AUTH-13.7': ('test_guest_redirect_for_each_protected_role_route', 'masyarakat'),
        'TC-AUTH-13.8': ('test_guest_redirect_for_each_protected_role_route', 'pemerintah'),
        'TC-AUTH-13.9': ('test_guest_redirect_for_each_protected_role_route', 'admin'),
        'TC-AUTH-14.1': ('test_server_rejects_blank_credentials', 'keduanya kosong'),
        'TC-AUTH-14.2': ('test_server_rejects_blank_credentials', 'email kosong'),
        'TC-AUTH-14.3': ('test_server_rejects_blank_credentials', 'password kosong'),
        'TC-AUTH-14.4': ('test_role_middleware_rejects_direct_url', 'masyarakat ke pemerintah'),
        'TC-AUTH-14.5': ('test_role_middleware_rejects_direct_url', 'masyarakat ke admin'),
        'TC-AUTH-14.6': ('test_role_middleware_rejects_direct_url', 'pemerintah ke admin'),
        'TC-AUTH-14.7': ('test_role_middleware_rejects_direct_url', 'pemerintah ke masyarakat'),
        'TC-AUTH-14.8': ('test_role_middleware_rejects_direct_url', 'admin ke pemerintah'),
        'TC-AUTH-14.9': ('test_role_middleware_rejects_direct_url', 'admin ke masyarakat'),
        'TC-AUTH-14.10': ('test_database_seeder_provides_login_accounts_for_all_roles', None),
    }
    for ident, (method, dataset) in mapping.items():
        filename = 'TC13AutentikasiLengkapTest.php' if ident.startswith('TC-AUTH-13.') else 'TC14KontrakServerTest.php'
        result.append((ident, ROOT / 'tests/Browser/PBI12' / filename, method, dataset))
    assert set(rows) == {entry[0] for entry in result}, 'Document IDs and mapping differ'
    return rows, result


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    rows, entries = manifest()
    # Discover provider names with PHPUnit itself, not previous JUnit totals.
    discovered = set()
    namespace = {'p': 'https://xml.phpunit.de/testSuite'}
    for path in dict.fromkeys(entry[1] for entry in entries):
        target = OUT / (path.stem + '.discovery.xml')
        subprocess.run(['php', 'vendor/bin/phpunit', '--do-not-cache-result',
                        '--list-tests-xml', str(target.relative_to(ROOT)), str(path.relative_to(ROOT))],
                       cwd=ROOT, check=True, stdout=subprocess.DEVNULL)
        discovered.update(node.get('id') for node in ET.parse(target).findall('.//p:testMethod', namespace))
    planned = set()
    for _, path, method, dataset in entries:
        cls = 'Tests\\Browser\\' + path.parent.name + '\\' + path.stem
        planned.add(cls + '::' + method + (('#' + dataset) if dataset else ''))
    assert planned == discovered, (planned - discovered, discovered - planned)
    print(f'Discovery verified: {len(entries)} documented execution IDs; no assumption about distinct requirements.', flush=True)
    results = []
    for ident, path, method, dataset in entries:
        cls = 'Tests\\Browser\\' + path.parent.name + '\\' + path.stem
        fullname = cls + '::' + method + (f' with data set "{dataset}"' if dataset else '')
        pattern = '/^' + re.escape(fullname) + '$/'
        result_dir = path.parent / '.runtime/individual'
        result_dir.mkdir(parents=True, exist_ok=True)
        xml = result_dir / (ident + '.xml')
        log = result_dir / (ident + '.log')
        argv = ['php', 'vendor/bin/phpunit', '--do-not-cache-result', '--display-deprecations',
                '--filter', pattern, '--log-junit', str(xml.relative_to(ROOT)), str(path.relative_to(ROOT))]
        item = dict(rows[ident], id=ident, file=str(path.relative_to(ROOT)), method=cls + '::' + method,
                    dataset=dataset, command=shlex.join(argv), xml=str(xml.relative_to(ROOT)),
                    log=str(log.relative_to(ROOT)))
        (result_dir / (ident + '.command.txt')).write_text(item['command'] + '\n')
        if xml.exists():
            xml.unlink()  # Never use stale evidence after a failed process launch.
        proc = subprocess.run(argv, cwd=ROOT, stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
        log.write_text(proc.stdout)
        item.update(returncode=proc.returncode, status='ERROR', assertions=0, detail='')
        match = re.search(r'Deprecations: (\d+)', proc.stdout)
        item['deprecations'] = int(match.group(1)) if match else 0
        if xml.exists():
            nodes = ET.parse(xml).getroot().findall('.//testcase')
            expected_name = method + (f' with data set "{dataset}"' if dataset else '')
            if len(nodes) != 1 or nodes[0].get('class') != cls or nodes[0].get('name') != expected_name:
                item['detail'] = 'Selection error: filter did not execute exactly the specified method/dataset.'
            else:
                node = nodes[0]
                item['assertions'] = int(node.get('assertions', '0'))
                error, failure, skipped = node.find('error'), node.find('failure'), node.find('skipped')
                if error is not None:
                    item.update(status='ERROR', detail=error.text or '', exception=error.get('type'))
                elif failure is not None:
                    item.update(status='FAIL', detail=failure.text or '', exception=failure.get('type'))
                elif skipped is not None:
                    item.update(status='BELUM DIJALANKAN', detail=skipped.text or '')
                elif proc.returncode == 0:
                    item['status'] = 'PASS'
                else:
                    item['detail'] = 'Nonzero exit code; see full process output.'
        else:
            item['detail'] = 'JUnit not produced; see full process output.'
        results.append(item)
        (OUT / 'results.json').write_text(json.dumps(results, indent=2, ensure_ascii=False) + '\n')
        print(f'{ident}: {item["status"]}; {item["assertions"]} assertions; {item["deprecations"]} deprecations; {method}'
              + (f' [{dataset}]' if dataset else ''), flush=True)
    print('Completed all independent selections.', flush=True)


if __name__ == '__main__':
    main()
