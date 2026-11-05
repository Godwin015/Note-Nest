import { useEffect } from 'react';
import { useEditor, EditorContent } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import CharacterCount from '@tiptap/extension-character-count';

type NoteEditorProps = {
    content: string;
    onChange: (content: string) => void;
};

function NoteEditor({ content, onChange }: NoteEditorProps) {
    const editor = useEditor({
        extensions: [
            StarterKit,
            CharacterCount.configure({
                limit: 5000,
            }),
        ],
        content,
        onCreate: ({ editor }) => {
            const normalizedContent = editor.getHTML();

            if (normalizedContent !== content) {
                onChange(normalizedContent);
            }
        },
        onUpdate: ({ editor }) => {
            onChange(editor.getHTML());
        },
    });


    // Synchronize the editor whenever the selected note changes.
    useEffect(() => {
        if (!editor || !editor.isInitialized) {
            return;
        }

        if (editor.getHTML() !== content) {
            editor.commands.setContent(content || '', {
                emitUpdate: false,
            });

            // Keep the parent's content state in HTML format.
            const normalizedContent = editor.getHTML();

            if (normalizedContent !== content) {
                onChange(normalizedContent);
            }
        }
    }, [editor, content, onChange]);


    return (
        <div className="editor-field">
            <div className="editor-field-label">Content</div>

            <div className="tiptap-toolbar">
                <button
                    type="button"
                    className={editor?.isActive('bold') ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().toggleBold().run()}
                    disabled={!editor}
                    aria-label="Bold"
                    title="Bold"
                >
                    <strong>B</strong>
                </button>

                <button
                    type="button"
                    className={editor?.isActive('italic') ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().toggleItalic().run()}
                    disabled={!editor}
                    aria-label="Italic"
                    title="Italic"
                >
                    <em>I</em>
                </button>

                <button
                    type="button"
                    className={editor?.isActive('underline') ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().toggleUnderline().run()}
                    disabled={!editor}
                    aria-label="Underline"
                    title="Underline"
                >
                    <u>U</u>
                </button>

                <button
                    type="button"
                    className={editor?.isActive('heading', { level: 2 }) ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() =>
                        editor?.chain().focus().toggleHeading({ level: 2 }).run()
                    }
                    disabled={!editor}
                    aria-label="Heading"
                    title="Heading"
                >
                    H
                </button>

                <button
                    type="button"
                    className={editor?.isActive('bulletList') ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().toggleBulletList().run()}
                    disabled={!editor}
                    aria-label="Bullet list"
                    title="Bullet list"
                >
                    •
                </button>

                <button
                    type="button"
                    className={editor?.isActive('orderedList') ? 'is-active' : ''}
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().toggleOrderedList().run()}
                    disabled={!editor}
                    aria-label="Numbered list"
                    title="Numbered list"
                >
                    1.
                </button>

                <button
                    type="button"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().undo().run()}
                    disabled={!editor}
                    aria-label="Undo"
                    title="Undo"
                >
                    ↶
                </button>

                <button
                    type="button"
                    onMouseDown={(event) => event.preventDefault()}
                    onClick={() => editor?.chain().focus().redo().run()}
                    disabled={!editor}
                    aria-label="Redo"
                    title="Redo"
                >
                    ↷
                </button>
            </div>

            <div className="tiptap-editor">
                <EditorContent editor={editor} />
            </div>

            <span className="character-count">
                {editor?.storage.characterCount.characters() ?? 0}/5000 characters
            </span>
        </div>
    );
}

export default NoteEditor;
