// shim-version: 1
/**
 * Bootstrap shim for @krokedil/wp-playground-tools. Committed per plugin and
 * scaffolded by `krokedil-playground init` — do not edit by hand; refresh with
 * `pnpm exec krokedil-playground init --update`.
 *
 * The real tooling lives in node_modules, which a fresh git worktree lacks.
 * This shim is the only pre-install code: Node built-ins only. It installs
 * node_modules when missing, then hands over to the packaged CLI.
 */
import { spawnSync } from 'node:child_process';
import fs from 'node:fs';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const ROOT = path.resolve( path.dirname( fileURLToPath( import.meta.url ) ), '..' );
const PKG = '@krokedil/wp-playground-tools';

if ( ! fs.existsSync( path.join( ROOT, 'node_modules', PKG ) ) ) {
	process.stderr.write( '▶ playground: installing Node dependencies (pnpm install)…\n' );
	// `pnpm run …` sets npm_execpath to pnpm's JS entry; use it so we don't
	// depend on a pnpm shim on PATH. Prefer --frozen-lockfile, fall back.
	const pnpm = ( args ) => {
		const execpath = process.env.npm_execpath;
		return execpath && /pnpm/.test( execpath )
			? spawnSync( process.execPath, [ execpath, ...args ], { cwd: ROOT, stdio: 'inherit' } )
			: spawnSync( 'pnpm', args, { cwd: ROOT, stdio: 'inherit' } );
	};
	const frozen = pnpm( [ 'install', '--frozen-lockfile' ] );
	if ( frozen.error || frozen.status !== 0 ) {
		process.stderr.write( '▶ playground: lockfile not usable as-is; running a normal install…\n' );
		const res = pnpm( [ 'install', '--no-frozen-lockfile' ] );
		if ( res.error || res.status !== 0 ) {
			process.stderr.write( '✖ playground: pnpm install failed — install pnpm (https://pnpm.io) and retry.\n' );
			process.exit( 1 );
		}
	}
}

const { main } = await import( PKG + '/cli' );
await main( process.argv.slice( 2 ) );
