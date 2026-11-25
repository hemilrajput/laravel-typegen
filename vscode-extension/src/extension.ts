import * as vscode from 'vscode';
import { spawn } from 'child_process';
import * as path from 'path';
import * as fs from 'fs';

const OUTPUT_CHANNEL_NAME = 'Laravel TypeGen';
const TYPESCRIPT_ATTRIBUTE_PATTERN = /#\[TypeScript(?:\b|\()/;

let output: vscode.OutputChannel;
let statusBarItem: vscode.StatusBarItem;

export function activate(context: vscode.ExtensionContext) {
    output = vscode.window.createOutputChannel(OUTPUT_CHANNEL_NAME);
    statusBarItem = createStatusBar(context);

    const onSaveDisposable = vscode.workspace.onDidSaveTextDocument(onDocumentSaved);
    context.subscriptions.push(onSaveDisposable, output, statusBarItem);

    context.subscriptions.push(
        vscode.commands.registerCommand('laravelTypegen.generate', runGenerate),
        vscode.commands.registerCommand('laravelTypegen.toggle', toggleOnSave)
    );

    output.appendLine('[Laravel TypeGen] Extension activated.');
}

function createStatusBar(context: vscode.ExtensionContext): vscode.StatusBarItem {
    const item = vscode.window.createStatusBarItem(vscode.StatusBarAlignment.Right, 100);
    item.command = 'laravelTypegen.toggle';
    updateStatusBar(item);
    item.show();
    context.subscriptions.push(item);
    return item;
}

function updateStatusBar(item: vscode.StatusBarItem) {
    const enabled = config<boolean>('enableOnSave', true);
    item.text = enabled ? '$(zap) TypeGen' : '$(zap) TypeGen (off)';
    item.tooltip = enabled
        ? 'Laravel TypeGen: Auto-generate on save (click to toggle)'
        : 'Laravel TypeGen: Auto-generate disabled (click to toggle)';
}

function onDocumentSaved(doc: vscode.TextDocument) {
    if (!config<boolean>('enableOnSave', true)) return;
    if (doc.languageId !== 'php') return;

    const isConfigFile = doc.fileName.replace(/\\/g, '/').endsWith('/config/typegen.php');
    if (!isConfigFile && !fileContainsAttribute(doc)) return;

    runGenerate();
}

function fileContainsAttribute(doc: vscode.TextDocument): boolean {
    const text = doc.getText();
    return TYPESCRIPT_ATTRIBUTE_PATTERN.test(text);
}

function runGenerate() {
    const workspaceRoot = getWorkspaceRoot();
    if (!workspaceRoot) {
        vscode.window.showErrorMessage('Laravel TypeGen: No workspace folder open.');
        return;
    }

    const artisan = path.join(workspaceRoot, 'artisan');
    if (!fs.existsSync(artisan)) {
        vscode.window.showErrorMessage('Laravel TypeGen: artisan not found. Is this a Laravel project?');
        return;
    }

    const php = config<string>('phpPath', 'php');
    const command = config<string>('artisanCommand', 'typescript:generate');

    if (config<boolean>('showOutputChannel', false)) {
        output.show(true);
    }

    output.appendLine(`\n[${timestamp()}] Running: ${php} artisan ${command}`);

    statusBarItem.text = '$(sync~spin) TypeGen';

    const proc = spawn(php, ['artisan', command], {
        cwd: workspaceRoot,
        shell: process.platform === 'win32',
    });

    proc.stdout.on('data', (data: Buffer) => output.append(data.toString()));
    proc.stderr.on('data', (data: Buffer) => output.append(data.toString()));

    proc.on('close', (code) => {
        if (code === 0) {
            output.appendLine(`[${timestamp()}] ✓ Type generation complete.`);
            vscode.window.setStatusBarMessage('$(check) TypeGen done', 3000);
        } else {
            output.appendLine(`[${timestamp()}] ✗ Type generation failed (exit ${code}).`);
            vscode.window.showWarningMessage(`Laravel TypeGen: Generation failed (exit ${code}). Check output for details.`);
        }
        updateStatusBar(statusBarItem);
    });

    proc.on('error', (err) => {
        output.appendLine(`[${timestamp()}] Error: ${err.message}`);
        vscode.window.showErrorMessage(`Laravel TypeGen: ${err.message}`);
        updateStatusBar(statusBarItem);
    });
}

function toggleOnSave() {
    const cfg = vscode.workspace.getConfiguration('laravelTypegen');
    const current = cfg.get<boolean>('enableOnSave', true);
    cfg.update('enableOnSave', !current, vscode.ConfigurationTarget.Global);
    updateStatusBar(statusBarItem);
    vscode.window.showInformationMessage(
        `Laravel TypeGen: Auto-generate on save ${!current ? 'enabled' : 'disabled'}.`
    );
}

function getWorkspaceRoot(): string | undefined {
    return vscode.workspace.workspaceFolders?.[0]?.uri.fsPath;
}

function config<T>(key: string, defaultValue: T): T {
    return vscode.workspace.getConfiguration('laravelTypegen').get<T>(key, defaultValue);
}

function timestamp(): string {
    return new Date().toLocaleTimeString();
}

export function deactivate() {}
