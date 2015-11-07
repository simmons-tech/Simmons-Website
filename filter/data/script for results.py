import csv

taken = []
occupants = {}

with open('results.csv', 'rb') as csvfile:
    reader = csv.reader(csvfile, delimiter=',')
    for row in reader:
        taken.append(row[4])
        occupants[row[2]] = row[4]
        
del taken[0] # spreadsheet heading

goodTaken = []
for room in taken:
    if room != '':
        goodTaken.append(room)
    else:
        print 'fuck'
