import React from 'react';
import ReactDOM from 'react-dom';
import App from './App';
const rootEl = document.getElementById('root');
const rootArgs = rootEl.dataset;

ReactDOM.render(<App id={rootArgs.id} user={rootArgs.user} />, rootEl);
