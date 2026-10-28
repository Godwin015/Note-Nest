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
  act,
  cleanup,
  fireEvent,
  render,
  screen,
  waitFor,
} from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import App from './NoteNestApp';
import { resetCsrfForTests } from './api';

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

const mockUser = {
  id: 1,
  name: 'Test User',
  email: 'test@example.com',
};

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

function mockResponse(
  response: unknown,
  status = 200,
) {
  return {
    ok: status >= 200 && status < 300,
    status,
    json: async () => response,
  };
}

function mockUserResponse() {
  return mockResponse({
    user: mockUser,
  });
}

/**
 * Creates a fetch mock that returns the supplied responses
 * in sequence.
 *
 * If the app makes more requests than expected, it returns
 * a default notes response instead of undefined.
 */
function mockFetchSequence(...responses: unknown[]) {
  const fetchMock = vi.fn();

  responses.forEach((response) => {
    fetchMock.mockResolvedValueOnce(response);
  });

  fetchMock.mockResolvedValue(
    mockResponse(paginatedResponse()),
  );

  return fetchMock;
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

  await act(async () => {
    editor!.innerHTML = `<p>${content}</p>`;
    fireEvent.input(editor!);
  });
}

/**
 * Initial dashboard requests:
 *
 * 1. GET /api/user
 * 2. GET /api/notes?page=1
 */
function mockInitialFetch(notes = mockNotes) {
  return mockFetchSequence(
    mockUserResponse(),
    mockResponse(paginatedResponse(notes)),
  );
}

beforeEach(() => {
  resetCsrfForTests();

  vi.stubGlobal(
    'fetch',
    mockInitialFetch(),
  );
});

afterEach(() => {
  cleanup();

  vi.restoreAllMocks();

  vi.unstubAllGlobals();

  resetCsrfForTests();
});

describe('NoteNest dashboard', () => {
  it('loads and displays notes from the API', async () => {
    renderApp();

    expect(
      await screen.findByText('My first NoteNest note'),
    ).toBeInTheDocument();

    expect(
      screen.getByText(
        'Testing the application interface.',
      ),
    ).toBeInTheDocument();
  });

  it('shows an empty state when there are no notes', async () => {
    vi.stubGlobal(
      'fetch',
      mockInitialFetch([]),
    );

    renderApp();

    expect(
      await screen.findByText(
        'A little space for your ideas',
      ),
    ).toBeInTheDocument();
  });

  it('opens the create-note form', async () => {
    const user = userEvent.setup();

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: /new note/i,
      }),
    );

    expect(
      screen.getByRole('dialog'),
    ).toBeInTheDocument();

    expect(
      screen.getByLabelText('Note title'),
    ).toBeInTheDocument();

    expect(
      document.querySelector(
        '[contenteditable="true"]',
      ),
    ).not.toBeNull();
  });

  it('submits a new note to the API', async () => {
    const user = userEvent.setup();

    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // CSRF initialization
      mockResponse(null, 204),

      // Create note response
      mockResponse({
        data: {
          ...mockNotes[0],
          id: 2,
          title: 'A note created in a test',
          content:
            '<p>This note was created automatically.</p>',
        },
      }),

      // Reload notes after creating
      mockResponse(
        paginatedResponse([
          {
            ...mockNotes[0],
            id: 2,
            title: 'A note created in a test',
            content:
              '<p>This note was created automatically.</p>',
          },
        ]),
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: /new note/i,
      }),
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
      screen.getByRole('button', {
        name: /^Create note$/,
      }),
    );

    await waitFor(() => {
      const createRequest =
        fetchMock.mock.calls.find(
          ([url, options]) =>
            url === '/api/notes' &&
            options?.method === 'POST',
        );

      expect(createRequest).toBeDefined();

      const requestOptions = createRequest![1]!;

      expect(requestOptions.method).toBe('POST');

      expect(
        new Headers(
          requestOptions.headers,
        ).get('Content-Type'),
      ).toBe('application/json');

      const requestBody = JSON.parse(
        requestOptions.body as string,
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
      await screen.findByText(
        'A note created in a test',
      ),
    ).toBeInTheDocument();
  });

  it('edits an existing note through the API', async () => {
    const user = userEvent.setup();

    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      mockResponse([]),

      // CSRF initialization
      mockResponse(null, 204),

      // Update note response
      mockResponse({
        data: {
          ...mockNotes[0],
          title: 'Updated NoteNest title',
        },
      }),

      // Reload notes after updating
      mockResponse(
        paginatedResponse([
          {
            ...mockNotes[0],
            title: 'Updated NoteNest title',
          },
        ]),
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: 'Edit',
      }),
    );

    const titleInput =
      screen.getByLabelText('Note title');

    await user.clear(titleInput);

    await user.type(
      titleInput,
      'Updated NoteNest title',
    );

    await user.click(
      screen.getByRole('button', {
        name: /^Save changes$/,
      }),
    );

    await waitFor(() => {
      const updateRequest =
        fetchMock.mock.calls.find(
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
      await screen.findByText(
        'Updated NoteNest title',
      ),
    ).toBeInTheDocument();
  });

  it('deletes a note after confirmation', async () => {
    const user = userEvent.setup();

    const confirmSpy = vi
      .spyOn(window, 'confirm')
      .mockReturnValue(true);

    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // CSRF initialization
      mockResponse(null, 204),

      // Delete response
      mockResponse({
        message: 'Note deleted successfully.',
      }),

      // Reload notes after deleting
      mockResponse(
        paginatedResponse([]),
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: 'Delete',
      }),
    );

    expect(confirmSpy).toHaveBeenCalledWith(
      'Delete "My first NoteNest note"? This cannot be undone.',
    );

    await waitFor(() => {
      const deleteRequest =
        fetchMock.mock.calls.find(
          ([url, options]) =>
            url === '/api/notes/1' &&
            options?.method === 'DELETE',
        );

      expect(deleteRequest).toBeDefined();
    });

    expect(
      await screen.findByText(
        'A little space for your ideas',
      ),
    ).toBeInTheDocument();
  });

  it('toggles a note pin through the API', async () => {
    const user = userEvent.setup();

    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // CSRF initialization
      mockResponse(null, 204),

      // Pin request response
      mockResponse({
        message:
          'Pin status updated successfully.',
        data: {
          ...mockNotes[0],
          is_pinned: true,
        },
      }),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: 'Pin note',
      }),
    );

    await waitFor(() => {
      const pinRequest =
        fetchMock.mock.calls.find(
          ([url, options]) =>
            url === '/api/notes/1/pin' &&
            options?.method === 'PATCH',
        );

      expect(pinRequest).toBeDefined();

      expect(
        JSON.parse(
          pinRequest![1]!.body as string,
        ),
      ).toEqual({
        is_pinned: true,
      });
    });

    expect(
      await screen.findByRole('button', {
        name: 'Unpin note',
      }),
    ).toBeInTheDocument();
  });

  it('sends search text to the API', async () => {
    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // Search request
      mockResponse(
        paginatedResponse(mockNotes),
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

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
      expect(
        fetchMock.mock.calls.some(
          ([url]) =>
            url ===
            '/api/notes?page=1&search=project',
        ),
      ).toBe(true);
    });
  });

  it('sends the selected category to the API', async () => {
    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // Category filter request
      mockResponse(
        paginatedResponse(mockNotes),
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

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
      expect(
        fetchMock.mock.calls.some(
          ([url]) =>
            url ===
            '/api/notes?page=1&category=work',
        ),
      ).toBe(true);
    });
  });

  it('shows validation errors returned by the API', async () => {
    const user = userEvent.setup();

    const fetchMock = mockFetchSequence(
      // Initial user request
      mockUserResponse(),

      // Initial notes request
      mockResponse(paginatedResponse()),

      // CSRF initialization
      mockResponse(null, 204),

      // Create note validation response
      mockResponse(
        {
          message:
            'The title field is required.',
          errors: {
            title: [
              'The title field is required.',
            ],
          },
        },
        422,
      ),
    );

    vi.stubGlobal('fetch', fetchMock);

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    await user.click(
      screen.getByRole('button', {
        name: /new note/i,
      }),
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
      screen.getByRole('button', {
        name: /^Create note$/,
      }),
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
      'Network unavailable',
    );
  });

  it('disables the next button on the last page', async () => {
    vi.stubGlobal(
      'fetch',
      mockInitialFetch(mockNotes),
    );

    renderApp();

    await screen.findByText(
      'My first NoteNest note',
    );

    expect(
      screen.getByRole('button', {
        name: /next/i,
      }),
    ).toBeDisabled();
  });
});