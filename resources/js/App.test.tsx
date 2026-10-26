import { MantineProvider } from '@mantine/core';
import {
  afterEach,
  beforeEach,
  describe,
  expect,
  it,
  vi,
} from 'vitest';
import {
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App from './NoteNestApp';

const mockNotes = [
  {
    id: 1,
    title: 'My first NoteNest note',
    content: 'Testing the application interface.',
    category: 'personal',
    is_pinned: false,
    created_at: '2026-10-22T10:00:00.000000Z',
    updated_at: '2026-10-22T10:00:00.000000Z',
  },
];

function paginatedResponse(
  notes = mockNotes,
  currentPage = 1,
  lastPage = 1,
  total = notes.length,
) {
  return {
    data: notes,
    current_page: currentPage,
    last_page: lastPage,
    total,
  };
}

function mockFetch(response: unknown, ok = true) {
  return vi.fn().mockResolvedValue({
    ok,
    status: ok ? 200 : 500,
    json: async () => response,
  });
}

function renderApp() {
  return render(
    <MantineProvider>
      <App />
    </MantineProvider>,
  );
}

/**
 * Gets the TipTap editor and types content into it.
 */
async function typeIntoEditor(
  _user: ReturnType<typeof userEvent.setup>,
  content: string,
) {
  const editor = document.querySelector(
    '[contenteditable="true"]',
  );

  expect(editor).not.toBeNull();

  editor!.innerHTML = `<p>${content}</p>`;

  fireEvent.input(editor!);
}

describe('NoteNest dashboard', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', mockFetch(paginatedResponse()));
  });

  afterEach(() => {
    cleanup();
    vi.restoreAllMocks();
    vi.unstubAllGlobals();
  });

  it('loads and displays notes from the API', async () => {
    renderApp();

    expect(
      await screen.findByText('My first NoteNest note'),
    ).toBeInTheDocument();

    expect(
      screen.getByText('Testing the application interface.'),
    ).toBeInTheDocument();
  });

  it('shows an empty state when there are no notes', async () => {
    vi.stubGlobal(
      'fetch',
      mockFetch(paginatedResponse([], 1, 1, 0)),
    );

    renderApp();

    expect(
      await screen.findByText('A little space for your ideas'),
    ).toBeInTheDocument();
  });

  it('opens the create-note form', async () => {
    const user = userEvent.setup();

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: /new note/i }),
    );

    expect(screen.getByRole('dialog')).toBeInTheDocument();

    expect(
      screen.getByLabelText('Note title'),
    ).toBeInTheDocument();

    expect(
      document.querySelector('[contenteditable="true"]'),
    ).not.toBeNull();
  });

  it('submits a new note to the API', async () => {
    const user = userEvent.setup();

    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse(),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 201,
        json: async () => ({
          message: 'Note created successfully.',
          data: {
            ...mockNotes[0],
            id: 2,
            title: 'A note created in a test',
            content:
              '<p>This note was created automatically.</p>',
          },
        }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () =>
          paginatedResponse([
            ...mockNotes,
            {
              ...mockNotes[0],
              id: 2,
              title: 'A note created in a test',
              content:
                '<p>This note was created automatically.</p>',
            },
          ], 1, 1, 2),
      });

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: /new note/i }),
    );

    await user.type(
      screen.getByLabelText('Note title'),
      'A note created in a test',
    );

    await typeIntoEditor(
      user,
      'This note was created automatically.',
    );

    await user.click(
      screen.getByRole('button', { name: /^Create note$/ }),
    );

    await waitFor(() => {
      const createRequest = fetchMock.mock.calls.find(
        ([url, options]) =>
          url === '/api/notes' &&
          options?.method === 'POST',
      );

      expect(createRequest).toBeDefined();

      expect(createRequest![1]).toEqual(
        expect.objectContaining({
          method: 'POST',
          headers: expect.objectContaining({
            'Content-Type': 'application/json',
          }),
        }),
      );

      const requestBody = JSON.parse(
        createRequest![1]!.body as string,
      );

      expect(requestBody).toEqual({
        title: 'A note created in a test',
        content:
          '<p>This note was created automatically.</p>',
        category: 'personal',
        color: '#FFFFFF',
      });
    });

    expect(
      await screen.findByText('A note created in a test'),
    ).toBeInTheDocument();
  });

  it('edits an existing note through the API', async () => {
    const user = userEvent.setup();

    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse(),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          message: 'Note updated successfully.',
          data: {
            ...mockNotes[0],
            title: 'Updated NoteNest title',
          },
        }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          data: [],
        }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () =>
          paginatedResponse([
            {
              ...mockNotes[0],
              title: 'Updated NoteNest title',
            },
          ]),
      });

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: 'Edit' }),
    );

    const titleInput = screen.getByLabelText('Note title');

    await user.clear(titleInput);

    await user.type(
      titleInput,
      'Updated NoteNest title',
    );

    await user.click(
      screen.getByRole('button', { name: /^Save changes$/ }),
    );

    await waitFor(() => {
      const updateRequest = fetchMock.mock.calls.find(
        ([url, options]) =>
          url === '/api/notes/1' &&
          options?.method === 'PATCH',
      );

      expect(updateRequest).toBeDefined();

      expect(updateRequest![1]).toEqual(
        expect.objectContaining({
          method: 'PATCH',
        }),
      );

      const requestBody = JSON.parse(
        updateRequest![1]!.body as string,
      );

      expect(requestBody).toEqual({
        title: 'Updated NoteNest title',
        content:
          '<p>Testing the application interface.</p>',
        category: 'personal',
        color: '#FFFFFF',
      });
    });

    expect(
      await screen.findByText('Updated NoteNest title'),
    ).toBeInTheDocument();
  });

  it('deletes a note after confirmation', async () => {
    const user = userEvent.setup();

    const confirmSpy = vi
      .spyOn(window, 'confirm')
      .mockReturnValue(true);

    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse(),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 204,
        json: async () => null,
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () =>
          paginatedResponse([], 1, 1, 0),
      });

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: 'Delete' }),
    );

    expect(confirmSpy).toHaveBeenCalledWith(
      'Delete "My first NoteNest note"? This cannot be undone.',
    );

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        '/api/notes/1',
        expect.objectContaining({
          method: 'DELETE',
        }),
      );
    });

    expect(
      await screen.findByText('A little space for your ideas'),
    ).toBeInTheDocument();
  });

  it('toggles a note pin through the API', async () => {
    const user = userEvent.setup();

    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse(),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => ({
          message: 'Pin status updated successfully.',
          data: {
            ...mockNotes[0],
            is_pinned: true,
          },
        }),
      })
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () =>
          paginatedResponse([
            {
              ...mockNotes[0],
              is_pinned: true,
            },
          ]),
      });

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: 'Pin note' }),
    );

    await waitFor(() => {
      expect(fetchMock).toHaveBeenCalledWith(
        '/api/notes/1/pin',
        expect.objectContaining({
          method: 'PATCH',
          body: JSON.stringify({
            is_pinned: true,
          }),
        }),
      );
    });

    expect(
      await screen.findByRole('button', {
        name: 'Unpin note',
      }),
    ).toBeInTheDocument();
  });

  it('sends search text to the API', async () => {
    const fetchMock = mockFetch(paginatedResponse());

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    fireEvent.change(
      screen.getByRole('textbox', {
        name: 'Search notes',
      }),
      {
        target: {
          value: 'project',
        },
      },
    );

    await waitFor(() => {
      expect(fetchMock).toHaveBeenLastCalledWith(
        '/api/notes?page=1&search=project',
      );
    });
  });

  it('sends the selected category to the API', async () => {
    const fetchMock = mockFetch(paginatedResponse());

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    fireEvent.change(
      screen.getByRole('combobox', {
        name: 'Filter notes by category',
      }),
      {
        target: {
          value: 'work',
        },
      },
    );

    await waitFor(() => {
      expect(fetchMock).toHaveBeenLastCalledWith(
        '/api/notes?page=1&category=work',
      );
    });
  });

  it('shows validation errors returned by the API', async () => {
    const user = userEvent.setup();

    const fetchMock = vi.fn()
      .mockResolvedValueOnce({
        ok: true,
        status: 200,
        json: async () => paginatedResponse(),
      })
      .mockResolvedValueOnce({
        ok: false,
        status: 422,
        json: async () => ({
          message: 'The title field is required.',
          errors: {
            title: [
              'The title field is required.',
            ],
          },
        }),
      });

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText('My first NoteNest note');

    await user.click(
      screen.getByRole('button', { name: /new note/i }),
    );

    await user.type(
      screen.getByLabelText('Note title'),
      'Test validation',
    );

    await typeIntoEditor(
      user,
      'Testing API validation errors.',
    );

    await user.click(
      screen.getByRole('button', { name: /^Create note$/ }),
    );

    expect(
      await screen.findAllByText(
        'The title field is required.',
      ),
    ).toHaveLength(2);
  });

  it('shows an error when the API cannot be reached', async () => {
    vi.stubGlobal(
      'fetch',
      vi.fn().mockRejectedValue(
        new Error('Network unavailable'),
      ),
    );

    renderApp();

    expect(
      await screen.findByRole('alert'),
    ).toHaveTextContent(
      'Could not connect to the server',
    );
  });

  it('disables the next button on the last page', async () => {
    vi.stubGlobal(
      'fetch',
      mockFetch(
        paginatedResponse(mockNotes, 1, 1, 1),
      ),
    );

    renderApp();

    await screen.findByText('My first NoteNest note');

    expect(
      screen.getByRole('button', { name: /next/i }),
    ).toBeDisabled();
  });
});
