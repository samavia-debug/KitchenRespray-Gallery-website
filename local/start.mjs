// Starts a throwaway local WordPress (WordPress Playground) with the plugin
// and theme from this folder mounted in. Nothing here touches the live site.
//   node local/start.mjs
import { spawn } from 'node:child_process';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const port = process.env.PORT || '9400';

const args = [
	'-y',
	'@wp-playground/cli@latest',
	'server',
	`--port=${port}`,
	'--php=8.0',
	'--mount=./wordpress/plugins/respray-portfolio:/wordpress/wp-content/plugins/respray-portfolio',
	'--mount=./wordpress/themes/respray-photos:/wordpress/wp-content/themes/respray-photos',
	'--blueprint=./local/blueprint.json',
];

const child = spawn('npx', args, { cwd: root, stdio: 'inherit', shell: true });
child.on('exit', (code) => process.exit(code ?? 0));
