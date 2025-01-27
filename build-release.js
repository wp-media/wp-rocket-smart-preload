//@ts-check
import path from 'node:path';
import process from 'node:process';
import fs from 'node:fs/promises';
import util from 'node:util';
import { exec } from 'node:child_process';
import esbuild from 'esbuild';

/**
 * TODO: 
 * - Use promisified exec instead of callback in all exec calls
 * - Abstract parts of the process into functions
 */
const execAsync = util.promisify(exec);
/**
 * @type {Array<NodeJS.Platform>}
 */
const OSs = ['linux', 'darwin'];

if (!OSs.includes(process.platform)) {
    coloredLog(
        '\nBy the moment, this script is compatible only with linux and MacOS systems.\n',
        'yellow'
    );
    console.log('Closing...');
    process.exit(0);
}
const cwd = process.cwd();
(async () => {
    const pkgString = await fs.readFile(path.resolve(cwd, './package.json'), {
        encoding: 'utf8'
    });
    const pkg = JSON.parse(pkgString);
    const dirName = `${pkg.name}`;
    const fileName = `${pkg.name}-v${pkg.version}.zip`;
    const inPath = path.resolve(cwd, './src'); // folder to zip
    const releaseFolder = path.resolve(cwd, `./release`);
    const outPathTemp = path.resolve(releaseFolder, `./${dirName}`); // name of output zip file
    const outPath = path.resolve(releaseFolder, `./${fileName}`); // name of output zip file
    const mainPluginFile = `${pkg.name}.php`;
    const mainPluginFilePath = path.resolve(outPathTemp, `./${mainPluginFile}`);
    const trackerFileName = 'rsp-tracker';
    const trackerFilePathIn = path.resolve(cwd, './src/assets/js/', `./${trackerFileName}.ts`);
    const trackerFilePathOut = path.resolve(outPathTemp, `./assets/js/${trackerFileName}.js`);
    // Doing typechecking before anything else
    await typescriptCheck();
    try {
        const result = await fs.access(
            inPath,
            fs.constants.R_OK | fs.constants.W_OK
        );
    } catch (e) {
        const errorName = util.getSystemErrorName(e.errno);
        if (errorName === 'ENOENT') {
            console.error(
                colorizeText(
                    "'src' directory does not exist, make sure the project is not broken",
                    'red'
                )
            );
            process.exit(1);
        } else {
            console.error(
                colorizeText(
                    "Something went wrong reading the 'src' directory\n",
                    'red'
                ),
                e
            );
            process.exit(1);
        }
    }
    try {
        const result = await fs.access(
            releaseFolder,
            fs.constants.R_OK | fs.constants.W_OK
        );
    } catch (e) {
        const errorName = util.getSystemErrorName(e.errno);
        if (errorName === 'ENOENT') {
            try {
                console.log(colorizeText(`Creating directory:`, 'blue'), releaseFolder);
                await fs.mkdir(releaseFolder);
            } catch (e) {
                console.error(
                    colorizeText(
                        `Could not create directory: ${releaseFolder}\n`,
                        'red'
                    ),
                    e
                );
                process.exit(1);
            }
        } else {
            console.error(
                colorizeText(
                    `Something went wrong reading path: ${releaseFolder}\n`,
                    'red'
                ),
                e
            );
            process.exit(1);
        }
    }
    // process to create the temp folder
    let result = null;
    try {
        result = await fs.access(
            outPathTemp,
            fs.constants.R_OK | fs.constants.W_OK
        );
    } catch (e) {
        if (util.getSystemErrorName(e.errno) !== 'ENOENT') {
            console.error(
                colorizeText(
                    `Something went wrong with the path: ${outPathTemp}\n`,
                    'red'
                ),
                e
            );
            process.exit(1);
        }
    }
    if (result === undefined) {
        await removeOutPathTemp(outPathTemp);
    }
    await transpileTypeScript([trackerFilePathIn], trackerFilePathOut);
    const command = `rsync -r --exclude='*.ts' ${inPath}/* ${outPathTemp}`;
    console.log(colorizeText(`Copying files to:`, 'blue'), `${outPathTemp}...\n`);
    exec(command, async (error, stdout, stderr) => {
        if (error) {
            console.error(
                colorizeText(
                    `Something when wrong copying the content:\nFrom: ${inPath}/* \nTo: ${outPathTemp}`,
                    'red'
                ),
                error
            );
            process.exit(1);
        }
        if (stdout) {
            console.log(stdout, '\n');
        }
        if (stderr) {
            console.log(stderr);
        }
        console.log(colorizeText('Writing version:', 'blue'), pkg.version, '\n');
        await replaceVersioInFiles(
            pkg.version,
            mainPluginFile,
            mainPluginFilePath,
            outPathTemp
        );
        processZip(outPathTemp, outPath, fileName, dirName, releaseFolder);
    });
})();
/**
 * @param {string} outPath
 * @param {string} fileName
 * @param {string} outPathTemp
 * @param {string} dirName
 * @param {string} releaseFolder
 *
 */
async function processZip(
    outPathTemp,
    outPath,
    fileName,
    dirName,
    releaseFolder
) {
    // process to create the zip
    let result = null;
    try {
        result = await fs.access(
            outPath,
            fs.constants.R_OK | fs.constants.W_OK
        );
    } catch (e) {
        if (util.getSystemErrorName(e.errno) !== 'ENOENT') {
            console.error(
                colorizeText(
                    `Something went wrong with the path: ${outPath}\n`,
                    'red'
                ),
                e
            );
            process.exit(1);
        }
    }
    if (result === undefined) {
        try {
            console.log(colorizeText('Removing file:', 'blue'), outPath, '\n');
            await fs.unlink(outPath);
        } catch (e) {
            console.error(
                colorizeText(
                    `Something went wrong while removing the file: ${outPath}\n`,
                    'red'
                ),
                e
            );
            process.exit(1);
        }
    }
    const command = `cd ${releaseFolder} && zip -r ${fileName} ${dirName} && cd ${cwd}`;
    console.log(colorizeText('Packing files:', 'blue'), `${fileName}...\n`);
    exec(command, async (error, stdout, stderr) => {
        if (error) {
            console.error(
                colorizeText(
                    'Something when wrong running the zip command:\n',
                    'red'
                ),
                error
            );
            process.exit(1);
        }
        if (stdout) {
            console.log(stdout);
        }
        if (stderr) {
            console.log(stderr);
        }
        // await removeOutPathTemp(outPathTemp);
        coloredLog('\nProcess finished successfuly...\n', 'green');
        coloredLog(`Find the packaged file in ${outPath}\n`, 'green');
    });
}
/**
 * @param {string} outPathTemp
 */
async function removeOutPathTemp(outPathTemp) {
    try {
        console.log(colorizeText('Removing temporary directory:', 'blue'), outPathTemp, '\n');
        await fs.rm(outPathTemp, { recursive: true, force: true });
    } catch (e) {
        console.error(
            colorizeText(
                `Something went wrong while removing the directory:`,
                'red'
            ),
            `${outPathTemp}\n`,
            e
        );
        process.exit(1);
    }
}
/**
 * Asynchronously removes a specified path from the file system.
 *
 * @param {string} pathToRemove - The path to be removed.
 * @param {boolean} [recursive=false] - Whether to remove directories and their contents recursively.
 * @returns {Promise<void>} - A promise that resolves when the path is removed.
 */
async function removePath(pathToRemove, recursive = false) {
    try {
        console.log(colorizeText('Removing path:', 'blue'), pathToRemove);
        await fs.rm(pathToRemove, { recursive, force: true });
    } catch (e) {
        console.error(
            colorizeText(
                'Something went wrong while removing the path:',
                'red'
            ),
            `${pathToRemove}\n`,
            e
        );
        process.exit(1);
    }
}
const colorSet = {
    red: '31',
    green: '32',
    yellow: '33',
    blue: '34'
};
/**
 * @param {string} text
 * @param {keyof typeof colorSet | "default"} [color='default']
 * @param {"log" | "error"} [consoleType]
 */
function coloredStd(text, color = 'default', consoleType = 'log') {
    /** @type {Array<keyof typeof colorSet>} */
    const colors = /** @type {Array<keyof typeof colorSet>} */ (
        Object.keys(colorSet)
    );
    if (consoleType !== 'log' && consoleType !== 'error') consoleType = 'log';
    if (color !== 'default' && !colors.includes(/** @type {any} */(color)))
        color = 'default';
    if (color === 'default') {
        console[consoleType](text);
    } else {
        console[consoleType](colorizeText(text, color));
    }
}
/**
 * @param {string} text
 * @param {keyof typeof colorSet | "default"} color
 */
function coloredLog(text, color = 'default') {
    coloredStd(text, color, 'log');
}
/**
 * @param {string} text
 */
function coloredError(text) {
    coloredStd(text, 'red', 'error');
}
/**
 * @param {string} text
 * @param {keyof typeof colorSet} color
 */
function colorizeText(text, color) {
    /** @type {Array<keyof typeof colorSet>} */
    const colors = /** @type {Array<keyof typeof colorSet>} */ (
        Object.keys(colorSet)
    );
    if (!colors.includes(color)) return text;
    return `\x1b[${colorSet[color]}m${text}\x1b[0m`;
}
/**
 *
 *
 * @param {string} version
 * @param {string} mainPluginFile
 * @param {string} mainPluginFilePath
 * @param {string} outPathTemp
 */
async function replaceVersioInFiles(
    version,
    mainPluginFile,
    mainPluginFilePath,
    outPathTemp
) {
    verifyVersion(version); // This throws an error if the version is invalid
    try {
        const toReplace = '0.0.0-dev';
        const content = await fs.readFile(mainPluginFilePath, {
            encoding: 'utf-8'
        });
        if (!content.includes(toReplace)) {
            await removeOutPathTemp(outPathTemp);
            coloredError(
                '\nCould not set the version of the plugin. Lines changed? Please check\n'
            );
            process.exit(1);
        }
        let replaced = content.replaceAll(
            toReplace,
            version
        );
        await fs.rm(mainPluginFile, { force: true });
        await fs.writeFile(mainPluginFilePath, replaced, { encoding: 'utf-8' });
    } catch (e) {
        console.error(
            colorizeText(
                `Something when wrong writing the version into the '${mainPluginFile}' file`,
                'red'
            ),
            e
        );
        process.exit(1);
    }
}
/**
 * @param {string} version
 * @return {true} Returns true if the version is valid
 * @throws {Error} Throws an error if the version is invalid
 */
function verifyVersion(version) {
    if (typeof version !== 'string')
        throw new Error(
            `The version must be 'string'. '${typeof version}' is invalid`
        );
    if (!version.match(/^\d\.\d\.\d$/)) {
        throw new Error(`The version is not valid. '${version}' is invalid`);
    }
    return true;
}

async function typescriptCheck() {
    const command = 'npx tsc --noEmit';
    try {
        console.log('Checking TypeScript...');
        // Run TypeScript type checking
        await execAsync(command);
    } catch (error) {
        coloredError('Checking failed!');
        console.error(colorizeText('Error:', 'red'), `${error.stdout}`);
        process.exit(1);
    }
    console.log(colorizeText('Typescript successfully checked.', 'green'), 'Proceeding with the build...', '\n');
}

/**
 * Transpiles JavaScript files using esbuild.
 *
 * @param {string[]} entryPoints - An array of entry points for the JavaScript files to be transpiled.
 * @param {string} outFile - The output path for the transpiled JavaScript file.
 * @returns {Promise<void>} A promise that resolves when the transpilation is complete.
 */
async function transpileTypeScript(entryPoints, outFile) {
    try {
        console.log('Transpiling TypeScript to JavaScript...', '\n');
        console.log(entryPoints);
        await esbuild.build({
            entryPoints: entryPoints, // Replace with the path to your TypeScript file
            bundle: false, // No bundling needed
            minify: true, // Minify the output
            outfile: outFile, // Output path for the transpiled JavaScript file
        });
        coloredLog('\nTranspilation succeeded!\n', 'green');
    } catch (error) {
        coloredError('Transpiling failed!');
        console.error(colorizeText('Error:', 'red'), `${error}`);
        process.exit(1);
    }
}